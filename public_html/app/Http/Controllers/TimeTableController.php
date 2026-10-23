<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\TimeTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Log;

class TimeTableController extends Controller
{
    public function index(Request $request)
    {

        $timeTables = TimeTable::all();
        $teachers = Master::select('teacher_name')
            ->distinct()
            ->get();
        return view('admin.timetable.index', compact('timeTables', 'teachers'));
    }
    public function syncTimeTableData()
{
    $client = new Client();
    $client->setApplicationName('example-app');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAuthConfig(storage_path('credentials.json'));

    $service = new Sheets($client);

    $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
    $sheetName = 'TT';

    $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
    $values = $response->getValues();

    if ($values) {
        TimeTable::truncate();
        $dataRows = array_slice($values, 1);

        foreach ($dataRows as $row) {
            if (count($row) >= 38) {
                $insertMaster = [
                    'student_name' => $row[0] ?? null,
                    'section' => $row[1] ?? null,
                    'class_syllabus' => $row[2] ?? null,
                    'monday_time' => $row[3] ?? null,
                    'monday_subject' => $row[4] ?? null,
                    'monday_teacher' => $row[5] ?? null,
                    'monday_hour' => $row[6] ?? null,
                    'monday_number' => $row[7] ?? null,
                    'tuesday_time' => $row[8] ?? null,
                    'tuesday_subject' => $row[9] ?? null,
                    'tuesday_teacher' => $row[10] ?? null,
                    'tuesday_hour' => $row[11] ?? null,
                    'tuesday_number' => $row[12] ?? null,
                    'wednesday_time' => $row[13] ?? null,
                    'wednesday_subject' => $row[14] ?? null,
                    'wednesday_teacher' => $row[15] ?? null,
                    'wednesday_hour' => $row[16] ?? null,
                    'wednesday_number' => $row[17] ?? null,
                    'thursday_time' => $row[18] ?? null,
                    'thursday_subject' => $row[19] ?? null,
                    'thursday_teacher' => $row[20] ?? null,
                    'thursday_hour' => $row[21] ?? null,
                    'thursday_number' => $row[22] ?? null,
                    'friday_time' => $row[23] ?? null,
                    'friday_subject' => $row[24] ?? null,
                    'friday_teacher' => $row[25] ?? null,
                    'friday_hour' => $row[26] ?? null,
                    'friday_number' => $row[27] ?? null,
                    'saturday_time' => $row[28] ?? null,
                    'saturday_subject' => $row[29] ?? null,
                    'saturday_teacher' => $row[30] ?? null,
                    'saturday_hour' => $row[31] ?? null,
                    'saturday_number' => $row[32] ?? null,
                    'sunday_time' => $row[33] ?? null,
                    'sunday_subject' => $row[34] ?? null,
                    'sunday_teacher' => $row[35] ?? null,
                    'sunday_hour' => $row[36] ?? null,
                    'sunday_number' => $row[37] ?? null,
                    'link' => $row[38] ?? null,
                    'student_number' => $row[39] ?? null,
                ];

                try {
                    TimeTable::create($insertMaster);
                } catch (\Exception $e) {
                    // Log the error for debugging
                    Log::error('Failed to insert row', ['row' => $row, 'error' => $e->getMessage()]);
                }
            } else {
                // Log if row data is insufficient
                Log::warning('Row data is insufficient', ['row' => $row]);
            }
        }

        $timestamp = Carbon::now();

        return response()->json([
            'message' => 'Data synced successfully',
            'last_synced' => $timestamp->format('Y-m-d H:i:s'),
        ]);
    } else {
        return response()->json(['message' => 'Data not found']);
    }
}


    public function timetableReport()
    {
        $timeTables = TimeTable::all();
        $teachers = Master::select('teacher_name')
            ->distinct()
            ->get();
        return view('timetable', compact('timeTables', 'teachers'));
    }

    public function getTeacherDOB(Request $request)
    {
        $teacherName = $request->input('teacher_name');
        $password = $request->input('dob');

        // Check if the teacher with the provided name and date of birth exists
        $teacher = Master::where('teacher_name', $teacherName)
            ->where('teacher_dob', $password)
            ->exists();

        if ($teacher) {
            return response()->json(['message' => 'Teacher found']);
        } else {
            return response()->json(['message' => 'Teacher not found']);
        }
    }
    public function studentTimetable(Request $request)
    {

        $timeTables = TimeTable::all();
        $students = Master::select('student_name')
            ->distinct()
            ->get();
        return view('admin.timetable.student-timetable', compact('timeTables', 'students'));
    }
}
