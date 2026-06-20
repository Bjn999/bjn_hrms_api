<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance_calenders;
use App\Models\Finance_months_periods;
use App\Models\Monthes;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinanceCalendersController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $com_code = $user->com_code;
            
            $data = Finance_calenders::with(['added:id,name', 'updatedby:id,name'])
                ->where("com_code", $com_code)
                ->orderBy('finance_yr', 'DESC')
                ->get();
            
            foreach ($data as $item) {
                // For finance calenders, used check might be different, let's keep it 0 or check if months have entries.
                $item->counterUsed = 0; 
            }

            $checkDataOpenCounter = Finance_calenders::where(["com_code" => $com_code, 'is_open' => 1])->count();
            
            return response()->json([
                'status' => true,
                'data' => $data,
                'has_open' => $checkDataOpenCounter > 0
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'finance_yr' => 'required|numeric|unique:finance_calenders,finance_yr,NULL,id,com_code,'.$user->com_code,
                'finance_yr_desc' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            DB::beginTransaction();
            
            $datatoInsert = [
                'finance_yr' => $request->finance_yr,
                'finance_yr_desc' => $request->finance_yr_desc,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'added_by' => $user->id,
                'com_code' => $user->com_code,
                'is_open' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $financeCalenderId = Finance_calenders::insertGetId($datatoInsert);
            
            if ($financeCalenderId) {
                $startDate = new DateTime($request->start_date); 
                $endDate = new DateTime($request->end_date);
                $dateInterval = new DateInterval('P1M');
                $datePeriod = new DatePeriod($startDate, $dateInterval, $endDate);
                
                foreach ($datePeriod as $date) {
                    $monthName_en = $date->format('F');
                    $dataParentmonth = Monthes::select('id')->where(['name_en' => $monthName_en])->first();
                    
                    if (!$dataParentmonth) continue;

                    $start_date_m = date('Y-m-01', strtotime($date->format('Y-m-d')));
                    $end_date_m = date('Y-m-t', strtotime($date->format('Y-m-d')));
                    $datediff = strtotime($end_date_m) - strtotime($start_date_m);

                    Finance_months_periods::insert([
                        'finance_calenders_id' => $financeCalenderId,
                        'month_id' => $dataParentmonth->id,
                        'finance_yr' => $request->finance_yr,
                        'start_date_m' => $start_date_m,
                        'end_date_m' => $end_date_m,
                        'year_and_month' => date('Y-m', strtotime($date->format('Y-m-d'))),
                        'number_of_days' => round($datediff / (60*60*24)) + 1,
                        'com_code' => $user->com_code,
                        'added_by' => $user->id,
                        'updated_by' => $user->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'start_date_for_pasma' => $start_date_m,
                        'end_date_for_pasma' => $end_date_m,
                    ]);
                }
            }

            DB::commit();
            
            return response()->json(['status' => true, 'message' => 'تم إضافة السنة المالية بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Finance_calenders::where(['id' => $id, 'com_code' => $user->com_code])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'السنة المالية غير موجودة'], 404);
            }
            if ($data->is_open != 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن تعديل سنة مالية مفتوحة'], 422);
            }

            $validator = Validator::make($request->all(), [
                'finance_yr' => ['required', 'numeric', Rule::unique('finance_calenders')->where('com_code', $user->com_code)->ignore($id)],
                'finance_yr_desc' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            DB::beginTransaction();

            $dataToUpdate = [
                'finance_yr' => $request->finance_yr,
                'finance_yr_desc' => $request->finance_yr_desc,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'updated_by' => $user->id,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $flag = Finance_calenders::where('id', $id)->update($dataToUpdate);

            if ($flag && ($data->start_date != $request->start_date || $data->end_date != $request->end_date)) {
                Finance_months_periods::where('finance_calenders_id', $id)->delete();
                
                $startDate = new DateTime($request->start_date); 
                $endDate = new DateTime($request->end_date);
                $dateInterval = new DateInterval('P1M');
                $datePeriod = new DatePeriod($startDate, $dateInterval, $endDate);
                
                foreach ($datePeriod as $date) {
                    $monthName_en = $date->format('F');
                    $dataParentmonth = Monthes::select('id')->where('name_en', $monthName_en)->first();
                    
                    if (!$dataParentmonth) continue;

                    $start_date_m = date('Y-m-01', strtotime($date->format('Y-m-d')));
                    $end_date_m = date('Y-m-t', strtotime($date->format('Y-m-d')));
                    $datediff = strtotime($end_date_m) - strtotime($start_date_m);

                    Finance_months_periods::insert([
                        'finance_calenders_id' => $id,
                        'month_id' => $dataParentmonth->id,
                        'finance_yr' => $request->finance_yr,
                        'start_date_m' => $start_date_m,
                        'end_date_m' => $end_date_m,
                        'year_and_month' => date('Y-m', strtotime($date->format('Y-m-d'))),
                        'number_of_days' => round($datediff / (60*60*24)) + 1,
                        'com_code' => $user->com_code,
                        'added_by' => $user->id,
                        'updated_by' => $user->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'start_date_for_pasma' => $start_date_m,
                        'end_date_for_pasma' => $end_date_m,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['status' => true, 'message' => 'تم تحديث السنة المالية بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Finance_calenders::where(['id' => $id, 'com_code' => $user->com_code])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'السنة المالية غير موجودة'], 404);
            }
            if ($data->is_open != 0) {
                return response()->json(['status' => false, 'message' => 'لا يمكن حذف سنة مالية مفتوحة'], 422);
            }

            Finance_calenders::where('id', $id)->delete();
            Finance_months_periods::where('finance_calenders_id', $id)->delete();

            return response()->json(['status' => true, 'message' => 'تم حذف السنة المالية بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function open(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Finance_calenders::where(['id' => $id, 'com_code' => $user->com_code])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'السنة المالية غير موجودة'], 404);
            }
            if ($data->is_open != 0) {
                return response()->json(['status' => false, 'message' => 'السنة المالية مفتوحة بالفعل'], 422);
            }

            $checkDataOpen = Finance_calenders::where(['com_code' => $user->com_code, 'is_open' => 1])->first();
            if ($checkDataOpen) {
                return response()->json(['status' => false, 'message' => 'يوجد سنة مالية مفتوحة مسبقاً، يجب إغلاقها أولاً'], 422);
            }
            
            Finance_calenders::where('id', $id)->update([
                'is_open' => 1,
                'updated_by' => $user->id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return response()->json(['status' => true, 'message' => 'تم فتح السنة المالية بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function months(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Finance_months_periods::where(['finance_calenders_id' => $id, 'com_code' => $user->com_code])->get();
            return response()->json(['status' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
