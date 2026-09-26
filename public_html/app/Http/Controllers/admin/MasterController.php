<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Master;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Sheets;
use Yajra\DataTables\Facades\DataTables;

class MasterController extends Controller
{
   
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Master::select('id','student_name','student_code','teacher_name','teacher_code','teacher_dob','hour_payment','profit_per_hour','payment_per_hour'); 
            return DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('action', function ($row) {
                        $button = '<button type="button" data-href="'.route('subjects.edit',$row->id).'" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                        $button .='<button data-href="'.route('delete',['subjects',$row->id]).'" class="btn btn-xs btn-primary btn-modal"><i class="fa fa-trash"></i></button>';
                        return $button;
                    })
                    ->filter(function ($instance) use ($request) {
                        if (!empty($request->get('search'))) {
                             $instance->where(function($w) use($request){
                                $search = $request->get('search');
                                $w->Where('name', 'LIKE', "%$search%");
                            });
                        }
                    })
                    ->rawColumns(['action'])
                    ->make(true);
        }
        return view('admin.master.index');
    }

   public function MastersSynkdata()
    {
        $client = new Client();
        $client->setApplicationName('example-app');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('credentials.json'));

        $service = new Sheets($client);

        $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
        $sheetName = 'MIXED';

        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        $values = $response->getValues();

        if ($values) {
            // Clear existing data from the 'Master' table
            Master::truncate();
    
            $dataRows = array_slice($values, 1);
        
            foreach ($dataRows as $row) {
                // Check if the row has enough elements
                if (count($row) >= 9) {
                    $insertMaster = [
                        'student_code' => $row[0],
                        'student_name' => $row[1],
                        'teacher_code' => $row[2],
                        'teacher_name' => $row[3],
                        'teacher_dob' => $row[4],
                        'hour_payment' => $row[5],
                        'profit_per_hour' => $row[6],
                        'payment_per_hour' => $row[7],
                        'team' => $row[8],
                    ];
        
                    Master::create($insertMaster);
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
