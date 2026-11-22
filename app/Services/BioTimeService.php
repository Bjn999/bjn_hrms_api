<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BioTimeService
{
    protected $serverUrl;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->serverUrl = rtrim(config('services.biotime.server_url') ?? env('BIOTIME_SERVER_URL'), '/');
        $this->username = config('services.biotime.username') ?? env('BIOTIME_USERNAME');
        $this->password = config('services.biotime.password') ?? env('BIOTIME_PASSWORD');
    }

    /**
     * Authenticate and get JWT token from BioTime.
     */
    public function getAuthToken()
    {
        if (empty($this->serverUrl) || empty($this->username) || empty($this->password)) {
            throw new Exception("إعدادات الاتصال بـ BioTime غير مكتملة في ملف البيئة .env");
        }

        try {
            // Using the standard JWT endpoint
            $response = Http::post("{$this->serverUrl}/jwt-api-token-auth/", [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->failed()) {
                Log::error("فشل تسجيل الدخول في BioTime: " . $response->body());
                throw new Exception("فشل الاتصال بسيرفر BioTime: تأكد من صحة اسم المستخدم وكلمة المرور ورابط السيرفر.");
            }

            $data = $response->json();
            return $data['token'] ?? $data['access'] ?? null;
        } catch (Exception $e) {
            Log::error("خطأ أثناء الاتصال بـ BioTime: " . $e->getMessage());
            throw new Exception("تعذر الاتصال بسيرفر BioTime: " . $e->getMessage());
        }
    }

    /**
     * Get Terminals by Area Code (Company)
     */
    private function getTerminalsByArea($areaCode, &$token)
    {
        $terminalsSn = [];
        $internalAreaId = $this->getAreaIdByCode($areaCode) ?? $areaCode;

        $response = Http::withHeaders([
            'Authorization' => "JWT {$token}",
            'Accept' => 'application/json',
        ])->get("{$this->serverUrl}/iclock/api/terminals/", [
            'area' => $internalAreaId,
        ]);

        if ($response->status() === 401) {
            Log::warning("صلاحية التوكن انتهت أثناء جلب الأجهزة، نقوم بجلب توكن جديد...");
            $token = $this->getAuthToken();
            $response = Http::withHeaders([
                'Authorization' => "JWT {$token}",
                'Accept' => 'application/json',
            ])->get("{$this->serverUrl}/iclock/api/terminals/", [
                'area' => $internalAreaId,
            ]);

            if ($response->failed()) {
                throw new Exception("فشل التصريح بعد تجديد التوكن عند جلب الأجهزة.");
            }
        }

        if ($response->failed()) {
            Log::error("فشل جلب الأجهزة من BioTime للشركة {$areaCode}: " . $response->body());
            throw new Exception("حدث خطأ أثناء جلب قائمة أجهزة البصمة.");
        }

        $result = $response->json();
        $records = $result['data'] ?? $result['results'] ?? [];

        foreach ($records as $device) {
            if (!empty($device['sn'])) {
                $terminalsSn[] = $device['sn'];
            }
        }

        // Throw an error if no terminals are found instead of returning empty array
        // so the controller knows to show an error message.
        if (empty($terminalsSn)) {
            Log::error("لا توجد أجهزة بصمة مضافة أو مرتبطة بهذه الشركة في نظام BioTime. يرجى التأكد من ربط الأجهزة بالشركة المطلوبة أولاً.");
            throw new Exception("لا توجد أجهزة بصمة مضافة أو مرتبطة بهذه الشركة. يرجى التأكد من ربط الأجهزة بالشركة المطلوبة أولاً.");
        }

        return $terminalsSn;
    }

    /**
     * Fetch all transactions from BioTime for the specified date range and area.
     */
    public function fetchTransactions($startDate, $endDate, $areaCode)
    {
        $token = $this->getAuthToken();
        if (!$token) {
            throw new Exception("فشل الحصول على رمز الصلاحية (Token) من سيرفر BioTime.");
        }

        // 1. Fetch terminals for this company (Area)
        $terminalsSn = $this->getTerminalsByArea($areaCode, $token);
        
        // Add an empty string to fetch manual punches (which have terminal_sn = "")
        $terminalsSn[] = '';
        
        $allTransactions = [];

        // Date format BioTime expects: YYYY-MM-DD HH:MM:SS
        $startTime = "{$startDate} 00:00:00";
        $endTime = "{$endDate} 23:59:59";

        // 2. Fetch transactions for each terminal
        foreach ($terminalsSn as $terminalSn) {
            $page = 1;
            $pageSize = 100;
            $hasMore = true;

            while ($hasMore) {
                try {
                    $queryParams = [
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'terminal_sn' => $terminalSn,
                        'page' => $page,
                        'page_size' => $pageSize,
                    ];

                    $response = Http::withHeaders([
                        'Authorization' => "JWT {$token}",
                        'Accept' => 'application/json',
                    ])->get("{$this->serverUrl}/iclock/api/transactions/", $queryParams);

                    // Handle Token Expiration (401 Unauthorized)
                    if ($response->status() === 401) {
                        Log::warning("صلاحية التوكن انتهت، نقوم بجلب توكن جديد للمتابعة...");
                        
                        // Fetch a fresh token
                        $token = $this->getAuthToken();
                        
                        // Try the same page again with the fresh token
                        $response = Http::withHeaders([
                            'Authorization' => "JWT {$token}",
                            'Accept' => 'application/json',
                        ])->get("{$this->serverUrl}/iclock/api/transactions/", $queryParams);

                        if ($response->failed()) {
                            Log::error("فشل نهائي في تصريح BioTime بعد تجديد التوكن: " . $response->body());
                            throw new Exception("بيانات الدخول (التوكن) غير مقبولة من BioTime بالرغم من التجديد.");
                        }
                    }

                    if ($response->failed()) {
                        Log::error("فشل سحب الحركات للجهاز {$terminalSn} الصفحة {$page}: " . $response->body());
                        throw new Exception("حدث خطأ أثناء جلب حركات البصمة من BioTime.");
                    }

                    $result = $response->json();
                    $records = $result['data'] ?? $result['results'] ?? [];
                    
                    if (empty($records)) {
                        $hasMore = false;
                    } else {
                        $allTransactions = array_merge($allTransactions, $records);
                        
                        // Check pagination
                        if (!empty($result['next'])) {
                            $page++;
                        } else {
                            $hasMore = false;
                        }
                    }
                } catch (Exception $e) {
                    Log::error("خطأ في جلب الصفحة {$page} للجهاز {$terminalSn}: " . $e->getMessage());
                    throw $e;
                }
            }
        }

        return $allTransactions;
    }

    /**
     * Create Area in BioTime
     */
    public function createArea($areaCode, $areaName)
    {
        $token = $this->getAuthToken();
        if (!$token) {
            throw new Exception("فشل الحصول على رمز الصلاحية (Token) من سيرفر BioTime.");
        }

        $response = Http::withHeaders([
            'Authorization' => "JWT {$token}",
            'Accept' => 'application/json',
        ])->post("{$this->serverUrl}/personnel/api/areas/", [
            'area_code' => $areaCode,
            'area_name' => $areaName,
        ]);

        if ($response->status() === 401) {
            $token = $this->getAuthToken();
            $response = Http::withHeaders([
                'Authorization' => "JWT {$token}",
                'Accept' => 'application/json',
            ])->post("{$this->serverUrl}/personnel/api/areas/", [
                'area_code' => $areaCode,
                'area_name' => $areaName,
            ]);
        }

        if ($response->failed()) {
            $errorDetails = $response->body();
            Log::error("فشل إنشاء Area في BioTime للشركة {$areaCode}: " . $errorDetails);
            
            $jsonError = json_decode($errorDetails, true);
            if (is_array($jsonError)) {
                if (isset($jsonError['non_field_errors'])) {
                    $errorText = implode(' ', $jsonError['non_field_errors']);
                    if (strpos($errorText, 'unique set') !== false) {
                        $errorMessage = "كود الشركة مسجل مسبقاً في نظام البصمة. يرجى اختيار كود آخر.";
                    } else {
                        $errorMessage = "خطأ من نظام البصمة: " . $errorText;
                    }
                } elseif (isset($jsonError['area_code'])) {
                    $errorText = implode(' ', $jsonError['area_code']);
                    if (strpos($errorText, 'already exists') !== false) {
                        $errorMessage = "كود الشركة (Area Code) موجود مسبقاً في نظام البصمة.";
                    } else {
                        $errorMessage = "خطأ في كود الشركة من نظام البصمة: " . $errorText;
                    }
                } else {
                    $errorMessage = "خطأ من نظام البصمة: " . json_encode($jsonError, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $errorMessage = "خطأ غير متوقع من نظام البصمة: " . $errorDetails;
            }
            
            throw new Exception($errorMessage);
        }

        return $response->json();
    }

    /**
     * Get Area ID from BioTime by Area Code
     */
    public function getAreaIdByCode($areaCode)
    {
        $token = $this->getAuthToken();
        if (!$token) {
            throw new Exception("فشل الحصول على رمز الصلاحية (Token) من سيرفر BioTime.");
        }

        $response = Http::withHeaders([
            'Authorization' => "JWT {$token}",
            'Accept' => 'application/json',
        ])->get("{$this->serverUrl}/personnel/api/areas/", [
            'area_code' => $areaCode
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['data']) && isset($data['data'][0]['id'])) {
                return $data['data'][0]['id'];
            }
        }

        return null;
    }

    /**
     * Create Employee in BioTime
     */
    public function createEmployee($empCode, $empName, $areaCode)
    {
        $token = $this->getAuthToken();
        if (!$token) {
            throw new Exception("فشل الحصول على رمز الصلاحية (Token) من سيرفر BioTime.");
        }

        // Fetch the actual internal Area ID from BioTime using the provided company_code (areaCode)
        $internalAreaId = $this->getAreaIdByCode($areaCode);
        
        if (!$internalAreaId) {
            // Fallback for edge cases or if it's already an ID
            $internalAreaId = (int)$areaCode;
        }

        $payload = [
            'emp_code' => (string)$empCode,
            'first_name' => $empName,
            'department' => 1,
            'area' => [ $internalAreaId ]
        ];

        $response = Http::withHeaders([
            'Authorization' => "JWT {$token}",
            'Accept' => 'application/json',
        ])->post("{$this->serverUrl}/personnel/api/employees/", $payload);

        if ($response->status() === 401) {
            $token = $this->getAuthToken();
            $response = Http::withHeaders([
                'Authorization' => "JWT {$token}",
                'Accept' => 'application/json',
            ])->post("{$this->serverUrl}/personnel/api/employees/", $payload);
        }

        if ($response->failed()) {
            $errorDetails = $response->body();
            Log::error("فشل إنشاء موظف في BioTime: " . $errorDetails);
            
            $jsonError = json_decode($errorDetails, true);
            $errorMessage = "خطأ من نظام البصمة أثناء إضافة الموظف.";
            if (is_array($jsonError)) {
                if (isset($jsonError['emp_code'])) {
                    $errorMessage = "رقم الموظف (الكود) موجود مسبقاً في نظام البصمة.";
                } else {
                    $errorMessage .= " " . json_encode($jsonError, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $errorMessage .= " " . $errorDetails;
            }
            
            throw new Exception($errorMessage);
        }

        return $response->json();
    }
}
