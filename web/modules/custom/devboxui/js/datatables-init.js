(function ($, Drupal, once) {
  Drupal.behaviors.devboxuiDataTables = {
    attach: function (context) {
      const tables = once('devboxui-datatables', '.vps-pricing-table', context);

      tables.forEach(table => {
        const dt = $(table).DataTable({
          dom: 'lfirtip',
          scrollX: true,
          paging: false,
          ordering: false,
          searching: true,
          search: {
            smart: false,
            regex: false,
            caseInsensitive: true
          }
        });

        dt.on('draw.dt', function () {
          const term = dt.search().trim();
          const body = dt.table().body();

          $(body).find('td').each(function () {
            // Remove previous highlights
            $(this).find('mark.dt-hl').contents().unwrap();

            if (!term) return;

            // Replace matching text inside cell HTML, preserving other tags
            const regex = new RegExp(term, 'gi');
            $(this).html(function (_, oldHtml) {
              return oldHtml.replace(regex, function (m) {
                return '<mark class="dt-hl">' + m + '</mark>';
              });
            });
          });
        });

      });
    }
  };
})(jQuery, Drupal, once);
