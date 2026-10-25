    <script src="{{mix('js/app.js')}}"></script>
    <!-- Custom JS Plugins -->


    <!-- DataTables  & Plugins -->
    <script src="{{asset('adminlte/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('adminlte/dataTables.bootstrap4.min.js')}}"></script>
    <script src="{{asset('adminlte/dataTables.responsive.min.js')}}"></script>
    <script src="{{asset('adminlte/responsive.bootstrap4.min.js')}}"></script>
    <script src="{{asset('adminlte/dataTables.buttons.min.js')}}"></script>
    <script src="{{asset('adminlte/buttons.bootstrap4.min.js')}}"></script>
    <script src="{{asset('adminlte/jszip.min.js')}}"></script>
    <script src="{{asset('adminlte/pdfmake.min.js')}}"></script>
    <script src="{{asset('adminlte/vfs_fonts.js')}}"></script>
    <script src="{{asset('adminlte/buttons.html5.min.js')}}"></script>
    <script src="{{asset('adminlte/buttons.print.min.js')}}"></script>
    <script src="{{asset('adminlte/buttons.colVis.min.js')}}"></script>
    
    <script>$(document).ready(function(){$('.select2').select2()})</script>
    <script>
      $(function () {
        var url = window.location;
        // for single sidebar menu
        $('ul.nav-sidebar a').filter(function () {
          return this.href == url;
        }).addClass('active');
        // for sidebar menu and treeview
        $('ul.nav-treeview a').filter(function () {
            return this.href == url;
        }).parentsUntil(".nav-sidebar > .nav-treeview")
            .css({'display': 'block'})
            .addClass('menu-open').prev('a')
            .addClass('active');
      });
    </script>
    <!-- Page specific script -->

    <script>
      toastr.options = {
        escapeHtml: true,
        closeButton: true,
        progressBar: true,
        newestOnTop: true,
        positionClass: 'toast-top-right',
        timeOut: 3500
      };

      if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
          pageLength: 25,
          lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
          responsive: true,
          autoWidth: false,
          language: {
            search: '',
            searchPlaceholder: 'Search records…',
            processing: 'Loading records…',
            emptyTable: 'No records available',
            zeroRecords: 'No matching records found'
          }
        });
      }

      $('[data-toggle="tooltip"]').tooltip();
    </script>

<script>
// Associate inherited form labels with their controls, including fetched dialogs.
(function () {
    let nextId = 0;
    function labelFields(root) {
        root.querySelectorAll('label').forEach(function (label) {
            if (label.querySelector('input,select,textarea')) return;
            let control = label.nextElementSibling;
            if (!control || !control.matches('input,select,textarea')) return;
            if (!control.id) control.id = 'tool-field-' + (++nextId);
            label.htmlFor = control.id;
        });
        root.querySelectorAll('input[type="search"]').forEach(function(input) {
            if (!input.getAttribute('aria-label')) input.setAttribute('aria-label','Search records');
        });
    }
    labelFields(document);
    $(document).on('shown.bs.modal draw.dt', function () { labelFields(document); });
})();
</script>
