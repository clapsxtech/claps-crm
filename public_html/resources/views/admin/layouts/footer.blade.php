      <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
          <b>Clapslearn</b> Admin
        </div>
        <span>© {{date('Y')}} Clapslearn. All rights reserved.</span>
      </footer>
    
      <!-- Control Sidebar -->
      <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
      </aside>
      <!-- /.control-sidebar -->
    </div>
    <!-- ./wrapper -->
    
    <script src="{{asset('js/app.js')}}"></script>
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

