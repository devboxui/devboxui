(function ($, Drupal, once) {
  Drupal.behaviors.devboxuiDataTables = {
    attach: function (context) {
      const tables = once('devboxui-datatables', '.vps-pricing-table', context);

      tables.forEach(table => {
        $(table).DataTable({
          dom: 'lfirtip',
          scrollX: true,   // Horizontal scrolling
          paging: false,   // Show all rows
          ordering: false,
          searching: true,  // Enable search box
        });
      });
    }
  };
})(jQuery, Drupal, once);