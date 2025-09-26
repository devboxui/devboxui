<?php

namespace Drupal\devboxui\Service;

use Drupal\user\Entity\User;
use Drupal\devboxui\VpsProviderManager;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * Service for handling DevBox batch operations.
 */
class DevBoxBatchService {

  /**
   * The VPS provider plugin manager.
   *
   * @var \Drupal\devboxui\VpsProviderManager
   */
  protected VpsProviderManager $vpsProviderManager;

  /**
   * Constructs a DevBoxBatchService object.
   *
   * @param \Drupal\devboxui\VpsProviderManager $vpsProviderManager
   *   The VPS provider plugin manager service.
   */
  public function __construct(VpsProviderManager $vpsProviderManager) {
    $this->vpsProviderManager = $vpsProviderManager;
  }

  /**
   * Starts a Drupal batch operation for VPS provisioning steps.
   *
   * @param array $commands
   *   The batch commands to execute.
   * @param string $title
   *   The batch title.
   */
  public function startBatch(array $commands, string $title = ''): void {
    $operations = [];
    foreach ($commands as $step => $command) {
      if (is_array($command)) {
        $paragraph_id = key($command);
        $callback = current($command);
        $cmd = next($command);
        $operations[] = [
          $callback,
          [$step, $paragraph_id],
        ];
      }
    }
    $batch = [
      'title' => $title,
      'operations' => $operations,
      'finished' => [get_class($this), 'finished'],
    ];
    batch_set($batch);
  }

  /**
   * Batch finished callback.
   */
  public static function finished($success, $results, $operations): void {
    // Add messages or handle results after batch
  }

  /**
   * Batch callback for provisioning a VPS.
   */
  public static function provision_vps($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    // Load the paragraph entity and get the response field.
    $paragraph = entityManage('paragraph', $paragraph_id);
    if ($paragraph->getType() != 'manual') {
      \Drupal::service('plugin.manager.vps_provider')->createInstance($paragraph->getType())->create_vps($paragraph);
    }
  }

  /**
   * Batch callback for deleting a VPS.
   */
  public static function delete_vps($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    // Load the paragraph entity and get the response field.
    $paragraph = entityManage('paragraph', $paragraph_id);
    if ($paragraph->getType() != 'manual') {
      \Drupal::service('plugin.manager.vps_provider')->createInstance($paragraph->getType())->delete_vps($paragraph);
    }
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_system_update($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    self::ssh_wrapper($paragraph_id, 'apt update', $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_system_upgrade($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    self::ssh_wrapper($paragraph_id, 'apt -y upgrade', $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_docker_install($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    self::ssh_wrapper($paragraph_id, 'for pkg in docker.io docker-doc docker-compose docker-compose-v2 podman-docker containerd runc; do apt remove $pkg; done; apt update; apt -y install ca-certificates curl; install -m 0755 -d /etc/apt/keyrings; curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc; chmod a+r /etc/apt/keyrings/docker.asc; echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}") stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null; apt update; apt -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin', $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_caddy_install($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    self::ssh_wrapper($paragraph_id, 'mkdir -p /root/caddy/sites', $context, TRUE);

    $email = entityManage('user', \Drupal::currentUser()->id())->get('mail')->getString();

    $auth_block = [
      "{",
      "email $email",
      "}",
      "(auth_protect) {",
      [
        "route {",
        [
          "forward_auth https://www.devboxui.com {",
          [
            "uri /user/login?destination=vhost-auth",
            "copy_headers Remote-User Remote-Email Remote-Name",
          ],
          "}",
        ],
        "}",
      ],
      "}",
      "import sites/*",
    ];

    $caddyfile = self::caddy_lines_to_string($auth_block);

    $log = self::ssh_wrapper($paragraph_id, <<<BASH
      cat <<'EOF' > /root/caddy/Caddyfile
      $caddyfile
      EOF
      BASH
      , $context, TRUE);

    $log = self::ssh_wrapper($paragraph_id, "docker rm -f caddy && docker run -d \
      --name caddy \
      --restart always \
      --network ddev_default \
      -p 80:80 -p 443:443 \
      -v /root/caddy:/etc/caddy \
      -v caddy_data:/data \
      caddy:2", $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_caddy_vhosts($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    $vhost_cmds = [];
    $paragraph = entityManage('paragraph', $paragraph_id);
    $vhosts = $paragraph->get('field_virtual_hosts')->getValue();
    foreach ($vhosts as $vhost) {
      $vhost_config = entityManage('paragraph', $vhost['target_id']);
      $host = trim($vhost_config->get('field_domain_subdomain')->getString());
      $container = trim($vhost_config->get('field_container_name')->getString());
      $port = trim(self::ssh_wrapper($paragraph_id, 'docker ps --filter "name='.$container.'" --format \'{{json .}}\' | jq -r \'.Ports | split(", ")[] | select(test("->80/")) | capture("(?<host>[^:]+):(?<port>[0-9]+)->80/tcp").port\'', $context, TRUE));

      $vhost_lines = [
        "$host {",
        $vhost_config->get('field_locked')->getString() ? "auth_protect" : null,
        ["reverse_proxy web:80"],
        "}",
      ];

      // Remove nulls (when not locked).
      $vhost_lines = array_filter($vhost_lines);
      $vhost_data = self::caddy_lines_to_string($vhost_lines);

      self::ssh_wrapper($paragraph_id, <<<BASH
        cat <<'EOF' > /root/caddy/sites/$host.caddy
        $vhost_data
        EOF
        BASH
        , $context, TRUE);
      # Fix inconsistencies.
      $log = self::ssh_wrapper($paragraph_id, "docker exec caddy caddy fmt --overwrite /etc/caddy/Caddyfile", $context, TRUE);
      # Reload caddy.
      $log = self::ssh_wrapper($paragraph_id, "docker exec caddy caddy reload --config /etc/caddy/Caddyfile", $context, TRUE);
    }
  }

  private static function caddy_lines_to_string(array $lines, int $indent = 0): string {
    $out = [];
    $increase_next_indent = false;

    foreach ($lines as $line) {
      if (is_array($line)) {
        $sub = self::caddy_lines_to_string($line, $indent + 8);
        $out = array_merge($out, explode("\n", $sub));
        $increase_next_indent = false;
      } else {
        $trimmed = trim($line);

        if ($trimmed === '{') {
          $out[] = str_repeat(' ', $indent) . $line;
          $increase_next_indent = true;
        } elseif ($trimmed === '}') {
          $out[] = str_repeat(' ', $indent) . $line;
          $increase_next_indent = false;
        } else {
          $out[] = str_repeat(' ', $indent + ($increase_next_indent ? 8 : 0)) . $line;

          // if this line itself ends with "{", prepare for indent
          $increase_next_indent = str_ends_with($trimmed, '{');
        }
      }
    }

    return implode("\n", $out);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_ssh_configs($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    // Set keep alive settings for SSH.
    self::ssh_wrapper($paragraph_id, "sed -i 's/^#\?TCPKeepAlive.*/TCPKeepAlive yes/' /etc/ssh/sshd_config; sed -i 's/^#\?ClientAliveInterval.*/ClientAliveInterval 60/' /etc/ssh/sshd_config; sed -i 's/^#\?ClientAliveCountMax.*/ClientAliveCountMax 5/' /etc/ssh/sshd_config; systemctl enable ssh; systemctl restart ssh", $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_ddev_install($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    # Install DDEV
    self::ssh_wrapper($paragraph_id, 'apt update && apt install -y curl; install -m 0755 -d /etc/apt/keyrings; curl -fsSL https://pkg.ddev.com/apt/gpg.key | gpg --dearmor | tee /etc/apt/keyrings/ddev.gpg > /dev/null; chmod a+r /etc/apt/keyrings/ddev.gpg; echo "deb [signed-by=/etc/apt/keyrings/ddev.gpg] https://pkg.ddev.com/apt/ * *" | tee /etc/apt/sources.list.d/ddev.list >/dev/null; apt update; apt -y install ddev; mkcert -install', $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_php_install($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_composer_install($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    self::ssh_wrapper($paragraph_id, 'apt update && apt install -y composer', $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_create_user($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    $sshUser = devboxui_normalize_uuid();
    // Create the user.
    self::ssh_wrapper($paragraph_id, "useradd -m -s /bin/bash $sshUser; groupadd docker; usermod -aG docker $sshUser; mkdir /home/$sshUser/.ssh; touch /home/$sshUser/.ssh/authorized_keys; chown -R $sshUser:$sshUser /home/$sshUser/.ssh; chmod 600 $sshUser:$sshUser /home/$sshUser/.ssh/authorized_keys", $context, TRUE);
    // Upload SSH pub key to the user.
    $userPubKey = entityManage('user', \Drupal::currentUser()->id())->get('field_ssh_public_key')->getString();
    self::ssh_wrapper($paragraph_id, "echo $userPubKey > /home/$sshUser/.ssh/authorized_keys", $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_ohmybash($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    // Install OhMyBash globally.
    self::ssh_wrapper($paragraph_id, 'apt update', $context, TRUE);
    self::ssh_wrapper($paragraph_id, 'apt -y install curl git', $context, TRUE);
    self::ssh_wrapper($paragraph_id, "rm -rf /usr/share/oh-my-bash; git clone --depth=1 https://github.com/ohmybash/oh-my-bash.git /usr/share/oh-my-bash; sed -i 's|^export OSH=.*|export OSH=/usr/share/oh-my-bash|' /usr/share/oh-my-bash/templates/bashrc.osh-template; sed -i 's|^OSH_THEME=.*|OSH_THEME=\"90210\"|' /usr/share/oh-my-bash/templates/bashrc.osh-template; cp /usr/share/oh-my-bash/templates/bashrc.osh-template /etc/skel/.bashrc", $context, TRUE);
    // Update the existing root user with OhMyBash.
    self::ssh_wrapper($paragraph_id, "cp /usr/share/oh-my-bash/templates/bashrc.osh-template /root/.bashrc", $context, TRUE);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_create_user_devbox($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    $sshUser = 'devbox';
    // Create the user.
    self::ssh_wrapper($paragraph_id, "useradd -m -s /bin/bash $sshUser", $context, TRUE);
    // Create docker group and add the user to it.
    self::ssh_wrapper($paragraph_id, "
      groupadd docker;
      usermod -aG docker $sshUser;
    ", $context, TRUE);
    // SSH key.
    self::ssh_wrapper($paragraph_id, "
      mkdir /home/$sshUser/.ssh;
      cp /root/.ssh/authorized_keys /home/$sshUser/.ssh/authorized_keys;
      chown -R $sshUser:$sshUser /home/$sshUser/.ssh;
      chmod 600 $sshUser:$sshUser /home/$sshUser/.ssh/authorized_keys;
    ", $context, TRUE);
  }

  public static function ssh_wrapper($paragraph_id, $command, &$context, $root = FALSE) {
    try {
      // Load the paragraph entity and get the response field.
      $paragraph = entityManage('paragraph', $paragraph_id);

      # Get private key from the current user's field.
      $user = entityManage('user', \Drupal::currentUser()->id());
      $private_key = $user->get('field_ssh_private_key')->getString();

      if ($paragraph->getType() != 'manual') {
        # Get the IP from the paragraph's field_response field.
        $paragraph_response = json_decode($paragraph->get('field_response')->getString(), TRUE);
        // get paragraph type
        $provider = $paragraph->getType();
        if ($provider == 'hetzner') {
          $host = $paragraph_response['public_net']['ipv4']['ip'];
        }
        else if ($provider == 'vultr') {
          $host = $paragraph_response['main_ip'];
        }
        else if ($provider == 'digitalocean') {
          $host = $paragraph_response['networks']['v4'][0]['ip_address'];
        }
        else if ($provider == 'akamai_cloud_linode') {
          $host = $paragraph_response['ipv4'][0];
        }
      }
      else {
        $host = $paragraph->get('field_server_ip')->getString();
      }

      if ($root) {
        if ($root == 'root') {
          $username = 'root';
        }
        else {
          $username = $root;
        }
      }
      else {
        $username = devboxui_normalize_uuid();
      }

      $ssh = new SSH2($host);
      $key = PublicKeyLoader::loadPrivateKey($private_key);

      $success = 0;
      $i = 0;
      do {
        $finished = ($i >= 10 || $success == 1);

        \Drupal::logger('devbox')->notice('SSH attempt #@attempt, finished: @finished, success: @success', [
          '@attempt' => $i + 1,
          '@finished' => $finished,
          '@success' => $success,
        ]);

        try {
          if ($ssh->login($username, $key)) {
            $success = 1;
            // Optionally break out of the loop immediately if successful
            break;
          }
        } catch (\Exception $e) {
          \Drupal::logger('devbox')->warning('SSH login attempt failed with exception: ' . $e->getMessage());
        }

        $i++;
        sleep(3); // Wait for 3 seconds before retrying
      } while (!$finished); // Continue until finished is true (non-zero)

      $result = $ssh->exec($command);

      \Drupal::logger('devbox')->notice('Executed command: @command on @host:<br> @result', [
        '@command' => $command,
        '@host' => $host,
        '@result' => $result,
      ]);

      return $result;
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $e->getMessage();
      \Drupal::logger('devbox')->error('Executed command: @command on @host:<br> @result', [
        '@command' => $command,
        '@host' => $host,
        '@result' => $e->getMessage(),
      ]);
      $context['message'] = t('An error occurred: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_provision_app($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    $app = entityManage('paragraph', $paragraph_id);
    $config = json_decode($app->get('field_saved_config')->getString(), TRUE);

    $options = [];
    $image = '';
    foreach ($config as $ck => $cv) {
      if (is_array($cv)) {
        if ($processed = self::processDockerConfig($ck, $cv)) {
          $options = array_merge($options, $processed);
        }
      }
      else {
        $options = array_merge($options, [$cv]);
      }
    }
    $cmd = [
      'docker run -d',
      '--name=' . devboxui_normalize_uuid($app->uuid()),
      !empty($options) ? implode(" \\\n", $options) : '',
    ];
    $command = implode(" \\\n", array_filter($cmd));

    // Run the command.
    self::ssh_app_wrapper($paragraph_id, $command, $context);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_delete_app($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    $app = entityManage('paragraph', $paragraph_id);
    $cmd = [
      'docker stop ' . devboxui_normalize_uuid($app->uuid()),
      'docker rm ' . devboxui_normalize_uuid($app->uuid()),
    ];
    $command = implode(";\n", $cmd);

    // Run the command.
    self::ssh_app_wrapper($paragraph_id, $command, $context);
  }

  public static function processDockerConfig($key, $values) {
    $output = [];
    if ($key == 'env_vars') {
      foreach ($values as $v) {
        $output[] = implode(' ', [
          '-e',
          implode('=', [
            strtoupper(key($v)),
            reset($v),
          ]),
        ]);
      }
    }
    else if ($key == 'volumes') {
      foreach ($values as $v) {
        $output[] = implode(' ', [
          '-v',
          implode(':', [
            $v['host_path'],
            $v['path'],
          ]),
        ]);
      }
    }
    else if ($key == 'ports') {
      foreach ($values as $v) {
        $output[] = implode(' ', [
          '-p',
          implode(':', [
            $v['external'],
            $v['internal'],
          ]),
        ]);
      }
    }
    else if ($key == 'custom') {
      foreach ($values as $v) {
        $output[] = implode('=', [
          '--'.key($v),
          reset($v),
        ]);
      }
    }
    else if ($key == 'caps') {
      foreach ($values as $v) {
        $output[] = implode('=', [
          '--'.str_replace('_', '-', key($v)),
          reset($v),
        ]);
      }
    }
    return $output;
  }

  public static function ssh_app_wrapper($paragraph_id, $command, &$context, $root = FALSE) {
    try {
      // Load the paragraph entity and get the response field.
      $app_paragraph = entityManage('paragraph', $paragraph_id);

      # Get private key from the current user's field.
      $user = entityManage('user', \Drupal::currentUser()->id());
      $private_key = $user->get('field_ssh_private_key')->getString();

      # Get the IP from the paragraph's field_response field.
      $devbox_paragraph = entityManage('paragraph', $app_paragraph->get('field_devbox_vps')->getString());
      if ($devbox_paragraph->get('type')->getString() != 'manual') {
        $devbox_response = json_decode($devbox_paragraph->get('field_response')->getString(), TRUE);
        $host = $devbox_response['public_net']['ipv4']['ip'];
      }
      else {
        $host = $devbox_paragraph->get('field_server_ip')->getString();
      }
      $username = devboxui_normalize_uuid();

      $ssh = new SSH2($host);
      $key = PublicKeyLoader::loadPrivateKey($private_key);

      $ssh->login($username, $key);
      $result = $ssh->exec($command);

      \Drupal::logger('devbox')->notice('Executed command: @command on @host:<br> @result', [
        '@command' => $command,
        '@host' => $host,
        '@result' => $result,
      ]);
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $e->getMessage();
      \Drupal::logger('devbox')->error('Executed command: @command on @host:<br> @result', [
        '@command' => $command,
        '@host' => $host,
        '@result' => $e->getMessage(),
      ]);
      $context['message'] = t('An error occurred: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_app_cleanup($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    $app = entityManage('paragraph', $paragraph_id);
    $command = 'rm -rf ~/config-'.$app->get('field_application')->getString();

    // Run the command.
    self::ssh_app_wrapper($paragraph_id, $command, $context);
  }

  /**
   * Batch callback for running SSH commands.
   * Use phpseclib to connect via SSH and run the command(s).
   */
  public static function ssh_reboot($step, $paragraph_id, &$context): void {
    $context['message'] = t('@step', ['@step' => $step]);

    // Run the command.
    self::ssh_wrapper($paragraph_id, 'shutdown -r now', $context, TRUE);
  }

}
