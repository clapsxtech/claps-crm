<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\Performance;
use App\Models\Topic;
use Google\Client;
use Google\Service\Sheets;
use Google_Service_Sheets_ValueRange;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function create()
    {
        $teachers = Master::select('teacher_name')
            ->distinct()
            ->orderBy('teacher_name', 'asc')
            ->get();
        $topics = Topic::select('id', 'name')->get();
        return view('performenceform')->with(compact('teachers', 'topics'));
    }
    public function store(Request $request)
    {
        $rules = [
            'teacher_name' => 'required',
            'teacher_code' => 'required',
            'student_name' => 'required',
            'topic' => 'required',
        ];

        $messages = [
            'teacher_code.required' => 'Teacher Code is required',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
        }

        try {
            $topicId = $request->input('topic');
            $selectedtopic = Topic::findOrFail($topicId);
            $performance = new Performance();
            $performance->teacher_name = $request->teacher_name;
            $performance->teacher_code = $request->teacher_code;
            $performance->student_name = $request->student_name;
            $performance->student_code = $request->student_code;
            $performance->topic      = $selectedtopic->name;
            $performance->remark             = $request->remark;
            $performance->created_at = Carbon::now();
            $performance->updated_at = Carbon::now();
            $password = $request->password;

            $hardcodedPassword = '121';
            if ($password !== $hardcodedPassword) {
                $response = ['success' => false, 'msg' => 'Passwords do not match.'];
                return response()->json($response);
            }

            $performance->save();
            $client = new Client();
            $client->setApplicationName('example-app');
            $client->setScopes([Sheets::SPREADSHEETS]);
            $client->setAuthConfig(storage_path('credentials.json'));

            $service = new Sheets($client);

            $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
            $sheetName = 'REVIEW';
            $values = [
                [
                    Carbon::createFromFormat('Y-m-d H:i:s', $performance->created_at)->format('d/m/Y H:i:s'),
                    $performance->teacher_name,
                    $performance->teacher_code,
                    $performance->student_name,
                    $performance->student_code,
                    $performance->topic,
                    $performance->remark,

                ],
            ];

            $range = $sheetName . '!A1:l1'; // Adjust the range as needed

            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values,
            ]);

            $params = [
                'valueInputOption' => 'RAW',
            ];

            $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

            return response()->json(['success' => true, 'msg' => 'Successfully created']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()]);
        }
    }
}
