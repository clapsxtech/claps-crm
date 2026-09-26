      <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
          <b>Version</b> 1.0.1.1
        </div>
        <p>Copyright © {{date('Y')}} - <a href="www.zentrona.in">Razbiller</a>.  All rights reserved</p>
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
    
    <script src="{{asset('adminlte/select2.min.js')}}"></script>
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
    <script src="{{asset('adminlte/toastr.min.js')}}"></script>
    <script src="{{asset('adminlte/bootstrap-colorpicker.min.js')}}"></script>
    <script src="{{asset('adminlte/jquery-ui.min.js')}}"></script>

    <script type="text/javascript" src="{{asset('core/js/jquery.magnific-popup.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('core/js/slick.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('core/js/custom.jquery.js')}}"></script>
    <script type="text/javascript" src="{{asset('adminlte/jquery.validate.min.js')}}"></script>
    {{-- <script>
      $(document).on('change','#category_id',function(){ 
        var catId = $(this).val();
        $.ajax({
                method: "POST",
                url: "{{route('subCategoryByCategoryID')}}",
                data: {
                    "_token": "{{ csrf_token() }}",
                    catId:catId
                    },
                dataType: "json",
                }).done(function( res ) {
                    $('#sub_category_id').html(res.html);
            });
       });
    </script> --}}