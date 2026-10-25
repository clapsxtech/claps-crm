@extends('erp.portal-layout')
@section('content')

    <section class="report-panel">
        <div class="container ">
            <div class="row">
                <!-- Filter Section -->
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <select class="form-control select2 filter" id="student_name" style="width:100%" required
                            name="student_name">
                            <option value="">Select Student</option>
                            @foreach ($students as $value)
                                <option data-student-name="{{ $value->student_name }}">{{ $value->student_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <input class="form-control filter" required placeholder="password(studentcode)" autocomplete="off"
                             name="student_code" id="student_code" type="text">

                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <label for="daterange">Date range</label><input class="form-control filter" required
                            placeholder="Choose dates" autocomplete="off"
                            name="daterange" id="daterange" type="text">
                    </div>
               </div>
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <select class="form-control select2 filter"  id="teacher_name" style="width:100%" required
                            name="teacher_name">
                            <option value="all">All Teachers</option>

                        </select>
                    </div>
                </div>
               
            </div>
            <!-- Table Section -->
            <div class="table-container">
              <span id="attendanceMessage" class="portal-status" role="status">Choose a teacher and enter your password to view attendance.</span>
                <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0">
                        <thead>
                            <tr>
                                <th>Teacher Name</th>
                                <th>Date</th>
                                <th>Hour</th>
                                <th>Subject</th>
                                <th>Topic</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <th></th>
                                <th></th>
                                <th id="hour-total"></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <!-- ... (scripts and jQuery code) ... -->

@endsection
@section('scripts')




<script src="{{ mix('js/report-dates.js') }}"></script>
<script>
         $(document).ready(function() {
        $('.select2').select2();
         $('#daterange').daterangepicker({
            autoUpdateInput: false,
            locale: {
                format: 'DD/MM/YYYY'
            },
            opens: 'left',
            autoApply: true,
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });

        // Update input field when a date range is selected
        $('#daterange').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            table.ajax.reload();
        });

        // Clear the input field when the date range is canceled
        $('#daterange').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
        });
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            dom: "<'row'<'col-sm-2'l><'col-sm-7 text-center'B><'col-sm-3'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-6'i><'col-sm-6'p>>",
            buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
           
            ajax: {
                url: "{{ route('student-attendance-report') }}",
                data: function(d) {
                    d.search = $('input[type="search"]').val();
                    d.teacher_name = $('#teacher_name').val();
                    d.student_code = $('#student_code').val();
                    d.student_name = $('#student_name').val();
                    d.start_date = $('#daterange').data('daterangepicker').startDate.format('YYYY/MM/DD');
                    d.end_date = $('#daterange').data('daterangepicker').endDate.format('YYYY/MM/DD');
                    
                }
            },
            columns: [{
                    data: 'teacher_name',
                    name: 'teacher_name'
                },
                {
                    data: 'date',
                    name: 'date'
                },
                {
                    data: 'hour_taken',
                    name: 'hour_taken'
                },
                {
                    data: 'subject',
                    name: 'subject'
                },
                {
                    data: 'topic',
                    name: 'topic'
                },
            ],
             footerCallback: function(row, data, start, end, display) {
                var api = this.api();
                var data;
                var intVal = function(i) {
                    if (typeof i === 'string') {
                        i = i.replace(/[\a-zA-Z₹]/g, '') * 1;
                    }
                    if (Number.isNaN(i)) {
                        return 0;
                    }
                    return i;
                };

                var hour_taken = api
                    .column(2, {
                        page: 'current'
                    })
                    .data()
                    .reduce(function(a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);

                $(api.column(0).footer()).html('Total');
                $(api.column(2).footer()).html(hour_taken);
            },
             lengthMenu: [
                [10, 50, 100, -1],
                [10, 50, 100, "All"]
            ],
            pageLength: -1
        });
      
        $('#student_name, #student_code').on('change', function() {
             $('#attendanceMessage').hide();
            var student_name = $('#student_name').val();
            var student_code = $('#student_code').val();
          
          if (student_name && student_code) {
              $.ajax({
                  url: '{{ route("get-teachers") }}', 
                  type: 'GET',
                  data: { student_name: student_name,student_code:student_code },
                  success: function(response) {
                    if (response.message) {
                    alert('Student code is incorrect.');
                    $('#teacher_name').empty().append('<option value="all">Select Teacher</option>');
                } else {
                    $('#teacher_name').empty();
                      $('#teacher_name').append('<option value="all">Select Teacher</option>');
                      
                      $.each(response.teachers, function(key, value) {
                          $('#teacher_name').append('<option data-teacher-name="' + value.teacher_name + '">' + value.teacher_name + '</option>');
                      });
                  }
                }
              });
          } else {
              $('#teacher_name').empty().append('<option value="all">Select Teacher</option>');
          }
          table.ajax.reload();
        });
        $('.filter').on('change', function() {
        table.ajax.reload();
    });
});
</script>

@endsection
