<?php
 
 namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Sheets;
use Yajra\DataTables\Facades\DataTables;

class GoogleSpreadsheetController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Teacher::select('id', 'teacher_name', 'teacher_code', 'status', 'type', 'subject', 'medium')->orderBy('id', 'ASC');
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
        return view('admin.teacher.index');
    }

    public function sheetData()
    {
        $client = new Client();
        $client->setApplicationName('example-app');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('credentials.json'));

        $service = new Sheets($client);

        $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
        $sheetName = 'TEACHER';

        // Fetch data from the specified sheet
        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        $values = $response->getValues();

        if ($values) {
            $dataRows = array_slice($values, 1);
            Teacher::truncate();
            foreach ($dataRows as $row) {
                $teacher = Teacher::where('teacher_code', $row[1])->where('teacher_name', $row[0])->first();

                if ($teacher) {
                    $teacher->update([
                        'teacher_name' => $row[0],
                        'teacher_code' => $row[1],
                        'status' => $row[2],
                        'type' => $row[3],
                        'subject' => $row[4],
                        'medium' => $row[5],
                    ]);
                } else {
                    // Create a new teacher record if not found
                    $teacher = Teacher::create([
                        'teacher_name' => $row[0],
                        'teacher_code' => $row[1],
                        'status' => $row[2],
                        'type' => $row[3],
                        'subject' => $row[4],
                        'medium' => $row[5],
                    ]);
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
}
