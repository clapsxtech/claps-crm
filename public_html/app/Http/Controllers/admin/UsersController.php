<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Modules\People\Services\StaffProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class UsersController extends Controller
{
    private $staff;

    public function __construct(StaffProvisioner $staff)
    {
        $this->staff = $staff;
    }

    public function index(Request $request)
    {
        $query = $this->staff->staffQuery($request->user());
        if ($request->ajax()) {
            return DataTables::of($query->select('id', 'name', 'email', 'phone'))
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '<button type="button" data-href="'.e(route('users.edit', $row->id)).'" class="btn btn-xs btn-primary edit_button" data-container=".modal_class" aria-label="Edit staff"><i class="fa fa-edit"></i></button>';
                })
                ->filter(function ($query) use ($request) {
                    $search = $request->input('search');
                    if (is_string($search) && $search !== '') {
                        $query->where('name', 'LIKE', '%'.$search.'%');
                    }
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.users.index');
    }

    public function create(Request $request)
    {
        $this->staff->staffQuery($request->user());
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        // Keep access failures outside the generic persistence handler.
        $this->staff->staffQuery($request->user());
        try {
            $this->staff->create($request->user(), $request->all());
            return response()->json(['success' => true, 'msg' => 'Successfully added.']);
        } catch (ValidationException $exception) {
            return $this->invalid($exception);
        } catch (\Throwable $exception) {
            // Database exceptions can contain password hashes and bank details. Never log their payload.
            Log::error('Unable to create a staff profile.');
            return response()->json(['success' => false, 'msg' => 'Unable to create the user.'], 500);
        }
    }

    public function edit(Request $request, $id)
    {
        $users = $this->staff->staffQuery($request->user())->findOrFail($id);
        return view('admin.users.edit', compact('users'));
    }

    public function update(Request $request, $id)
    {
        $this->staff->staffQuery($request->user())->findOrFail($id);
        try {
            $this->staff->update($request->user(), $id, $request->all());
            return response()->json(['success' => true, 'msg' => 'Successfully updated.']);
        } catch (ValidationException $exception) {
            return $this->invalid($exception);
        } catch (\Throwable $exception) {
            Log::error('Unable to update a staff profile.');
            return response()->json(['success' => false, 'msg' => 'Unable to update the user.'], 500);
        }
    }

    public function Profile(Request $request)
    {
        $users = $this->staff->staffQuery($request->user())->findOrFail($request->user()->getKey());
        // Use the same validated form instead of the older incompatible duplicate profile template.
        return view('admin.users.edit', compact('users'));
    }

    private function invalid(ValidationException $exception)
    {
        $errors = $exception->errors();
        return response()->json([
            'success' => false,
            'msg' => collect($errors)->flatten()->first(),
            'errors' => $errors,
        ], 422);
    }
}
