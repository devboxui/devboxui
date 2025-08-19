(function ($, Drupal) {
  Drupal.behaviors.devboxuiDataTables = {
    attach: function (context, settings) {
      // Initialize DataTables only once per table.
      $('.vps-pricing-table', context).once('devboxui-datatables').each(function () {
        $(this).DataTable();
      });
    }
  };
})(jQuery, Drupal);