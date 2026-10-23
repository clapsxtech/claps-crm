<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <title>Mark report</title>
    <link rel="stylesheet" href="{{ asset('frontent_form/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>body{background:#063436;color:#fff}main{max-width:1100px;margin:auto;padding:3rem 1rem}.report{background:#fff;color:#173333;padding:1.25rem;border-radius:12px;margin:1.25rem 0}label{display:block}.status{min-height:1.5rem;margin:1rem 0}</style>
</head>
<body class="public-page">
<main>
    <h1>Mark report</h1>
    <p>{{ $isAdministrator ? "Your administrator account can view all results." : "Verify your teacher details to view your students’ results." }}</p>
    <form id="report-access" autocomplete="off">
        @csrf
        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="teacher_name">Teacher</label>
                <select class="form-control" id="teacher_name" name="teacher_name" required>
                    <option value="{{ $isAdministrator ? 'all' : '' }}">{{ $isAdministrator ? 'All teachers (administrator)' : 'Select teacher' }}</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->teacher_name }}">{{ $teacher->teacher_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-3">
                @if ($isAdministrator)
                    <p class="mt-4">Administrator access verified.</p>
                    <input type="hidden" id="dob" name="dob" value="">
                @else
                    <label for="dob">Password (dd/mm/yyyy)</label>
                    <input class="form-control" type="password" id="dob" name="dob" required maxlength="255" autocomplete="off">
                @endif
            </div>
            <div class="col-md-4 mb-3">
                <label for="student_name">Student</label>
                <select class="form-control" id="student_name" name="student_name"><option value="all">{{ $isAdministrator ? 'All students' : 'All my students' }}</option></select>
            </div>
        </div>
        <button type="submit" class="btn btn-light" id="view-report">View results</button>
    </form>
    <p id="report-status" class="status" role="status" aria-live="polite">No results are loaded until your details are verified.</p>
    <div class="report" id="report" hidden>
        <div class="table-responsive">
            <table class="table table-bordered"><thead><tr><th>Student name</th><th>Exam date</th><th>Subject</th><th>Chapter/topic</th><th>Mark scored</th><th>Out of</th></tr></thead><tbody id="report-rows"></tbody></table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <button type="button" class="btn btn-outline-secondary" id="previous-page">Previous</button>
            <span id="page-status"></span>
            <button type="button" class="btn btn-outline-secondary" id="next-page">Next</button>
        </div>
    </div>
</main>
<script>
(function () {
    const form = document.getElementById('report-access');
    const student = document.getElementById('student_name');
    const report = document.getElementById('report');
    const rows = document.getElementById('report-rows');
    const status = document.getElementById('report-status');
    const submit = document.getElementById('view-report');
    const previous = document.getElementById('previous-page');
    const next = document.getElementById('next-page');
    let controller = null, generation = 0, start = 0, total = 0;
    const length = 50;
    const allStudentsLabel = @json($isAdministrator ? 'All students' : 'All my students');
    function clearReport() {
        generation++; if (controller) controller.abort();
        report.hidden = true; rows.replaceChildren(); submit.disabled = false;
        previous.disabled = true; next.disabled = true;
    }
    function resetStudents() {
        student.replaceChildren(new Option(allStudentsLabel, 'all'));
    }
    ['teacher_name', 'dob'].forEach(id => {
        document.getElementById(id).addEventListener('input', function () {
            clearReport(); resetStudents(); start = 0;
            status.textContent = 'Verify your details to view results.';
        });
    });
    student.addEventListener('change', function () {
        clearReport(); start = 0; status.textContent = 'Select View results to apply this student filter.';
    });
    async function post(url, data, signal) {
        const response = await fetch(url, {method: 'POST', credentials: 'same-origin', cache: 'no-store', signal,
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: data});
        if (!response.ok) throw new Error(response.status === 401 || response.status === 419
            ? 'Your session has expired. Reload this page and sign in again.'
            : 'Unable to load results. Check your teacher details and try again.');
        return response.json();
    }
    async function load(loadStudents) {
        clearReport(); const requestGeneration = generation; controller = new AbortController();
        const signal = controller.signal; submit.disabled = true; status.textContent = 'Loading your results…';
        const data = new FormData(form); data.set('start', start); data.set('length', length); data.set('draw', generation);
        try {
            if (loadStudents) {
                const selected = student.value;
                const lookup = new FormData(form); lookup.set('student_name', 'all');
                const result = await post(@json(route('get-students-mark')), lookup, signal);
                if (requestGeneration !== generation) return;
                resetStudents();
                result.students.forEach(item => student.add(new Option(item.student_name, item.student_name)));
                student.value = Array.from(student.options).some(option => option.value === selected) ? selected : 'all';
                data.set('student_name', student.value);
            }
            const result = await post(@json(route('markreport')), data, signal);
            if (requestGeneration !== generation) return;
            total = Number(result.recordsFiltered) || 0;
            result.data.forEach(item => {
                const row = document.createElement('tr');
                ['student_name','examination_date','subject','chapter','mark_scored','total_mark'].forEach(key => {
                    const cell = document.createElement('td'); cell.textContent = item[key] == null ? '' : item[key]; row.appendChild(cell);
                }); rows.appendChild(row);
            });
            if (!result.data.length) {
                const row = document.createElement('tr'); const cell = document.createElement('td');
                cell.colSpan = 6; cell.textContent = 'No results match this selection.'; row.appendChild(cell); rows.appendChild(row);
            }
            document.getElementById('page-status').textContent = total ? (start + 1) + '–' + Math.min(start + length, total) + ' of ' + total : '0 results';
            previous.disabled = start === 0; next.disabled = start + length >= total;
            report.hidden = false; status.textContent = 'Results loaded.';
        } catch (error) {
            if (requestGeneration === generation && error.name !== 'AbortError') status.textContent = error.message;
        } finally {
            if (requestGeneration === generation) submit.disabled = false;
        }
    }
    form.addEventListener('submit', event => {event.preventDefault(); start = 0; load(true);});
    previous.addEventListener('click', () => {if (start > 0) {start = Math.max(0, start - length); load(false);}});
    next.addEventListener('click', () => {if (start + length < total) {start += length; load(false);}});
    window.addEventListener('pagehide', clearReport);
})();
</script>
</body>
</html>
