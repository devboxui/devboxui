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
          },
          autoWidth: false,
          fixedHeader: true,
          columnDefs: [
            { width: "250px", targets: "_all" }
          ],
        });

        dt.on('draw.dt', function () {
          const term = (dt.search() || '').trim();
          const body = dt.table().body();

          $(body).find('td').each(function () {
            // Remove previous highlights
            $(this).find('mark.dt-hl').contents().unwrap();

            if (!term) return;

            // Highlight matching text in HTML, preserving all other tags
            const regex = new RegExp(term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');

            $(this).html(function (_, oldHtml) {
              return oldHtml.replace(regex, function (match) {
                return '<mark class="dt-hl">' + match + '</mark>';
              });
            });
          });
        });

        // Initial highlight on page load
        dt.draw();
      });
    }
  };
})(jQuery, Drupal, once);
