<?php

namespace App\Http\Controllers\admin;
use App\Models\Students;
use Google\Client;
use Google\Service\Sheets;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class StudentsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Students::select('id', 'student_name', 'student_code', 'status', 'hour_rate', 'category', 'source', 'hours')->orderBy('id', 'ASC');
            return DataTables::of($data)
                ->addIndexColumn()
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                        $instance->where(function ($w) use ($request) {
                            $search = $request->get('search');
                            $w->Where('name', 'LIKE', "%$search%");
                        });
                    }
                })
                ->make(true);
        }
        return view('admin.students.index');
    }

    public function sheetData()
    {
        $client = new Client();
        $client->setApplicationName('example-app');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('credentials.json'));

        $service = new Sheets($client);

        $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
        $sheetName = 'STUDENT';

        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        $values = $response->getValues();

        if ($values) {
            $dataRows = array_slice($values, 1);
            Students::truncate();
            foreach ($dataRows as $row) {
                $insertStudent = [
                    'category' => $row[0],
                    'student_name' => $row[1],
                    'status' => $row[2],
                    'student_code' => $row[3],
                    'hour_rate' => $row[4],
                    'category' => $row[5],
                    'source' => $row[6],
                    'hours' => $row[7],
                ];
                Students::updateOrCreate(
                    ['student_name'=> $insertStudent['student_name'], 'student_code' => $insertStudent['student_code']],
                    $insertStudent
                );

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
}
