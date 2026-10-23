<?php

namespace App\Http\Controllers;

use App\Models\DueReport;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Sheets;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class DueController extends Controller
{
    public function index(Request $request)
    {
        $student_name = DueReport::select('student_name')->distinct()->get();
        $status = DueReport::select('current_status')->distinct()->get();
        $section = DueReport::select('section')->distinct()->get();
        $admin_staff = DueReport::select('admin_staff')->distinct()->get();


        if ($request->ajax()) {
            $data = DueReport::select( 
            'id',
            'student_name',
            'student_code',
            'phone',
            'status',
            'hour_rate',
            'monthly',
            'advance_expected',
            'section',
            'admin_staff',
            'last_month_balance',
            'recived',
            'total_advance',
            'hour_taken',
            'c_amount',
            'error',
            'balance',
            'current_status'); 
            return DataTables::of($data)
                    ->addIndexColumn()
                    ->filter(function ($instance) use ($request) {
                        if ($request->get('student_name') && $request->get('student_name') != 'all') {
                            $instance->where('student_name', $request->get('student_name'));
                        }
                        if ($request->get('admin_staff') && $request->get('admin_staff') != 'all') {
                            $instance->where('admin_staff', $request->get('admin_staff'));
                        }
                        if ($request->get('status') && $request->get('status') != 'all') {
                            $instance->where('current_status', $request->get('status'));
                        }
                         if ($request->get('section') && $request->get('section') != 'all') {
                            $instance->where('section', $request->get('section'));
                        }
                        if (!empty($request->get('search'))) {
                             $instance->where(function($w) use($request){
                                $search = $request->get('search');
                                $w->Where('name', 'LIKE', "%$search%");
                            });
                        }
                    })
                    ->make(true);
        }
        return view('admin.duereport.index')->with(compact('student_name','status','admin_staff','section'));
    }

    public function syncDueReportData()
    {
        $client = new Client();
        $client->setApplicationName('example-app');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('credentials.json'));

        $service = new Sheets($client);

        $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
        $sheetName = 'Due';

        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        $values = $response->getValues();

        if ($values) {
            $dataRows = array_slice($values, 1);
            $records = [];
       
            foreach ($dataRows as $row) {
                if (empty($row[0])) {
                    continue;
                }

                if (count($row) < 20) {
                    throw new \UnexpectedValueException('A due-report spreadsheet row has fewer than 20 columns.');
                }

                    $records[] = [
                        'student_name' => $row[0],
                        'student_code' => $row[1],
                        'phone' => $row[2],
                        'status' => $row[3],
                        'hour_rate' => $row[4],
                        'monthly' => $row[6],
                        'advance_expected' => $row[9],
                        'section' => $row[10],
                        'admin_staff' => $row[11],
                        'last_month_balance' => $row[12],
                        'recived' => $row[13],
                        'total_advance' => $row[14],
                        'hour_taken' => $row[15],
                        'c_amount' => $row[16],
                        'error' => $row[17],
                        'balance' => $row[18],
                        'current_status' => $row[19],

                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
            }

            if (empty($records)) {
                throw new \UnexpectedValueException('The due-report spreadsheet contains no importable rows.');
            }

            DB::transaction(function () use ($records) {
                DueReport::query()->delete();
                foreach (array_chunk($records, 500) as $chunk) {
                    DueReport::insert($chunk);
                }
            });
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
