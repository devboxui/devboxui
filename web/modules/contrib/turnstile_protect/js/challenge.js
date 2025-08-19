function turnstileProtectAutoSubmit(token) {
  setTimeout(function() {
    document.getElementById("turnstile-protect-challenge-form").submit();
  }, 1000);
}
