<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Error;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewAbsenceMarked;
use App\Events\AbsenceMarked;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class AttendanceController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {

            $this->authorize('create', Attendance::class);
            $validated = $request->validate([
                'user_id'    => 'required|exists:users,id',
                'date'       => 'required|date',
                'type'       => 'required|string|in:absence,lateness,overtime,early_leave',
                'hours'      => 'nullable|numeric|min:0',
                'reason'     => 'nullable|string|max:255',
            ]);

            $validated['created_by'] = auth()->id();

            $attendance = Attendance::create($validated);

                $notifiable = User::findOrFail($attendance->user_id);
                $message = [
                    'key' => 'notif.new_absence_marked_details',
                    'params' => ['hours' => $attendance->hours],
                ];
                $link = 'employees/' . $attendance->user_id. '/suivi';

            Notification::send($notifiable, new NewAbsenceMarked($message, $link, 'notif.new_absence_marked'));
            broadcast(new AbsenceMarked($attendance) )->toOthers();

            return response()->json([
                'message'    => 'Attendance saved successfully',
                'attendance' => $attendance,
            ], 201);

        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getAttendances($id, Request $request)
    {
        try {
            $from = $request->query('Attendancefrom');
            $to = $request->query('Attendanceto');

            $start = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfMonth();
            $end   = $to  ? Carbon::parse($to)->endOfDay()   : Carbon::now()->endOfMonth();

            $attendanceRecords = Attendance::where('user_id', $id)
                                ->whereBetween('created_at', [$start, $end])
                                ->with('creator')
                                ->get();

            $overtime = $attendanceRecords->where('type', 'overtime')->sum('hours');
            $missed   = $attendanceRecords->whereIn('type', ['absence','lateness','early_leave'])->sum('hours');
            $net      = $overtime - $missed;

            return response()->json([
                'attendanceRecords' => $attendanceRecords,
                'overtime' => $overtime,
                'missed'   => $missed,
                'net'      => $net
            ], 201);

        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $attendance = Attendance::findOrFail($id);
            $this->authorize('delete', $attendance);
            $attendance->delete();

            return response()->json(['message' => 'Attendance supprimé avec succès'], 201);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
