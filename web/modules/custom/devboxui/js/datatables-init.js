(function ($, Drupal, once) {
  Drupal.behaviors.devboxuiDataTables = {
    attach: function (context) {
      const tables = once('devboxui-datatables', '.vps-pricing-table', context);

      tables.forEach(table => {
        $(table).DataTable({
          scrollX: true,   // Horizontal scrolling
          paging: false,   // Show all rows
          info: false,     // Hide "Showing X of Y"
          searching: true,  // Enable search box
          columnDefs: [
            { width: "900px", targets: "_all" } // all columns
          ]
        });
      });
    }
  };
})(jQuery, Drupal, once);