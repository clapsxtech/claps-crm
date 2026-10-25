@extends('erp.portal-layout')
@section('content')

<section class="report-panel">
    <h1>Teacher timetable</h1>
    <p>Enter your teacher name and credential to view only your lessons.</p>
    <form id="timetable-access" autocomplete="off">
        @csrf
        <div class="row">
            <div class="col-md-5 mb-3">
                <label for="teacher_name">Teacher name</label>
                <input class="form-control" id="teacher_name" name="teacher_name" required maxlength="255" autocomplete="off">
            </div>
            <div class="col-md-4 mb-3">
                <label for="dob">Password (dd/mm/yyyy)</label>
                <input class="form-control" id="dob" name="dob" type="password" required maxlength="255" autocomplete="off">
            </div>
            <div class="col-md-3 mb-3 align-self-end">
                <button class="btn btn-primary" type="submit" id="view-timetable">View timetable</button>
            </div>
        </div>
    </form>
    <p class="status" id="success-message" role="status" aria-live="polite">Verify your details to view your timetable.</p>
    <div id="schedule" hidden>
        @foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
            <section class="schedule">
                <h2 class="h5">{{ ucfirst($day) }}</h2>
                <div class="table-responsive">
                    <table class="table table-bordered" id="{{ $day }}-timetable">
                        <thead><tr><th>Time</th><th>Meeting</th><th>Student name</th><th>Subject</th><th>Hours</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</section>

@endsection
@section('scripts')
<script>
(function () {
    const form = document.getElementById('timetable-access');
    const schedule = document.getElementById('schedule');
    const message = document.getElementById('success-message');
    const button = document.getElementById('view-timetable');
    let controller = null;
    let generation = 0;
    function clearSchedule() {
        generation++;
        if (controller) controller.abort();
        schedule.hidden = true;
        schedule.querySelectorAll('tbody').forEach(body => body.replaceChildren());
        button.disabled = false;
    }
    form.addEventListener('input', function () {
        clearSchedule();
        message.textContent = 'Verify your details to view your timetable.';
    });
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearSchedule();
        const requestGeneration = generation;
        controller = new AbortController();
        button.disabled = true;
        message.textContent = 'Checking your details…';
        try {
            const response = await fetch(@json(route('get-teacher-dob')), {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: new FormData(form), signal: controller.signal
            });
            if (!response.ok) throw new Error('Unable to verify your details. Check your teacher name and password.');
            const result = await response.json();
            if (requestGeneration !== generation) return;
            Object.entries(result.days).forEach(([day, lessons]) => {
                const body = document.querySelector('#' + day + '-timetable tbody');
                lessons.forEach(lesson => {
                    const row = document.createElement('tr');
                    [lesson.time, null, lesson.student_name, lesson.subject, lesson.hour].forEach((value, index) => {
                        const cell = document.createElement('td');
                        if (index === 1 && lesson.link) {
                            const link = document.createElement('a');
                            link.href = lesson.link; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'Join meeting'; cell.appendChild(link);
                        } else cell.textContent = value || '—';
                        row.appendChild(cell);
                    });
                    body.appendChild(row);
                });
                if (!lessons.length) {
                    const cell = document.createElement('td'); cell.colSpan = 5; cell.textContent = 'No lessons scheduled.';
                    const row = document.createElement('tr'); row.appendChild(cell); body.appendChild(row);
                }
            });
            schedule.hidden = false;
            message.textContent = 'Your timetable is ready.';
        } catch (error) {
            if (requestGeneration === generation && error.name !== 'AbortError') message.textContent = error.message;
        } finally {
            if (requestGeneration === generation) button.disabled = false;
        }
    });
    window.addEventListener('pagehide', clearSchedule);
})();
</script>
@endsection
