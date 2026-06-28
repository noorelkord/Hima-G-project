<?php

namespace App\Services;

use App\Models\Contract;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class ContractPdfService
{
    public static function generate(Contract $contract): string
    {
        $contract->load([
            'tenant',
            'host',
            'property.governorate',
            'property.city',
            'property.neighborhood',
        ]);

        $types = [
            'apartment'  => 'شقة',
            'villa'      => 'فيلا',
            'land'       => 'أرض',
            'chalet'     => 'شاليه',
            'commercial' => 'تجاري',
            'parking'    => 'موقف',
        ];

        $statusMap = [
            'active'    => 'نشط',
            'expired'   => 'منتهي',
            'cancelled' => 'ملغي',
        ];

        $months     = $contract->start_date->diffInMonths($contract->end_date);
        $total      = number_format($contract->price * $months, 2);
        $price      = number_format($contract->price, 2);
        $contractNo = str_pad($contract->id, 6, '0', STR_PAD_LEFT);
        $today      = now()->format('Y-m-d');
        $now        = now()->format('Y-m-d H:i');

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: "Arial", sans-serif;
                font-size: 13px;
                color: #1a1a1a;
                direction: rtl;
                padding: 20px;
            }
            .header {
                text-align: center;
                border-bottom: 3px solid #1B4332;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .header h1 { font-size: 20px; color: #1B4332; }
            .header p  { color: #666; font-size: 11px; }
            .contract-id {
                text-align: center;
                background: #f0f7f4;
                border: 1px solid #1B4332;
                padding: 8px;
                margin-bottom: 20px;
                font-size: 13px;
                font-weight: bold;
                color: #1B4332;
            }
            .section { margin-bottom: 15px; border: 1px solid #ddd; }
            .section-title {
                background: #1B4332;
                color: white;
                padding: 7px 12px;
                font-size: 12px;
                font-weight: bold;
            }
            .section-body { padding: 10px; }
            table.info { width: 100%; border-collapse: collapse; }
            table.info tr { border-bottom: 1px solid #eee; }
            table.info td { padding: 5px 4px; font-size: 12px; }
            table.info td.label { font-weight: bold; color: #555; width: 150px; }
            .price-box {
                background: #f0f7f4;
                border: 2px solid #1B4332;
                padding: 12px;
                text-align: center;
                margin: 15px 0;
            }
            .price-box .amount { font-size: 24px; font-weight: bold; color: #1B4332; }
            .price-box .lbl { color: #666; font-size: 11px; }
            .terms {
                background: #fffbe6;
                border: 1px solid #f0ad00;
                padding: 12px;
                margin-bottom: 20px;
                font-size: 12px;
                line-height: 2;
            }
            .sig-table { width: 100%; margin-top: 30px; }
            .sig-table td {
                text-align: center;
                width: 50%;
                padding-top: 10px;
                border-top: 2px solid #1B4332;
            }
            .sig-name { font-weight: bold; color: #1B4332; }
            .sig-role { color: #666; font-size: 11px; }
            .footer {
                text-align: center;
                margin-top: 20px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
                color: #999;
                font-size: 10px;
            }
        </style>
        </head>
        <body>

        <div class="header">
            <h1>منصة حمى للإيجار</h1>
            <p>Hima Rental Platform </p>
        </div>

        <div class="contract-id">
            عقد إيجار رقم: #' . $contractNo . '
            &nbsp;|&nbsp;
            الحالة: ' . ($statusMap[$contract->status] ?? $contract->status) . '
            &nbsp;|&nbsp;
            تاريخ الإصدار: ' . $today . '
        </div>

        <div class="section">
            <div class="section-title">بيانات المستأجر</div>
            <div class="section-body">
                <table class="info">
                    <tr>
                        <td class="label">الاسم الكامل:</td>
                        <td>' . $contract->tenant->first_name . ' ' . $contract->tenant->second_name . ' ' . $contract->tenant->third_name . ' ' . $contract->tenant->last_name . '</td>
                    </tr>
                    <tr><td class="label">رقم الهوية:</td><td>' . $contract->tenant->national_id . '</td></tr>
                    <tr><td class="label">رقم الهاتف:</td><td>' . ($contract->tenant->phone ?? 'غير محدد') . '</td></tr>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">بيانات صاحب العقار</div>
            <div class="section-body">
                <table class="info">
                    <tr>
                        <td class="label">الاسم الكامل:</td>
                        <td>' . $contract->host->first_name . ' ' . $contract->host->second_name . ' ' . $contract->host->third_name . ' ' . $contract->host->last_name . '</td>
                    </tr>
                    <tr><td class="label">رقم الهوية:</td><td>' . $contract->host->national_id . '</td></tr>
                    <tr><td class="label">رقم الهاتف:</td><td>' . ($contract->host->phone ?? 'غير محدد') . '</td></tr>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">بيانات العقار</div>
            <div class="section-body">
                <table class="info">
                    <tr><td class="label">اسم العقار:</td><td>' . $contract->property->title . '</td></tr>
                    <tr><td class="label">النوع:</td><td>' . ($types[$contract->property->type] ?? $contract->property->type) . '</td></tr>
                    <tr><td class="label">المحافظة:</td><td>' . ($contract->property->governorate->name ?? 'غير محدد') . '</td></tr>
                    <tr><td class="label">المدينة:</td><td>' . ($contract->property->city->name ?? 'غير محدد') . '</td></tr>
                    <tr><td class="label">الحي:</td><td>' . ($contract->property->neighborhood->name ?? 'غير محدد') . '</td></tr>
                    <tr><td class="label">الشارع:</td><td>' . ($contract->property->street ?? 'غير محدد') . '</td></tr>
                    <tr><td class="label">عدد الغرف:</td><td>' . ($contract->property->rooms ?? 'غير محدد') . '</td></tr>
                    <tr><td class="label">المساحة:</td><td>' . ($contract->property->area_m2 ? $contract->property->area_m2 . ' م²' : 'غير محدد') . '</td></tr>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-title">تفاصيل العقد</div>
            <div class="section-body">
                <table class="info">
                    <tr><td class="label">تاريخ البداية:</td><td>' . $contract->start_date->format('Y-m-d') . '</td></tr>
                    <tr><td class="label">تاريخ النهاية:</td><td>' . $contract->end_date->format('Y-m-d') . '</td></tr>
                    <tr><td class="label">مدة العقد:</td><td>' . $months . ' شهر</td></tr>
                </table>
            </div>
        </div>

        <div class="price-box">
            <div class="lbl">قيمة الإيجار الشهري المتفق عليه</div>
            <div class="amount">' . $price . ' ₪</div>
            <div class="lbl">الإجمالي للمدة الكاملة: ' . $total . ' ₪</div>
        </div>

        <div class="terms">
            <strong>الشروط والأحكام:</strong><br>
            ١. يلتزم المستأجر بدفع قيمة الإيجار في موعده المحدد.<br>
            ٢. يلتزم المستأجر بالحفاظ على العقار وعدم إلحاق الضرر به.<br>
            ٣. لا يحق للمستأجر التنازل عن العقد لطرف ثالث دون موافقة صاحب العقار.<br>
            ٤. يحق لأي من الطرفين إنهاء العقد وفق سياسات المنصة.<br>
            ٥. هذا العقد ملزم قانونياً وفق أحكام القانون الفلسطيني المعمول به.
        </div>

        <table class="sig-table">
            <tr>
                <td>
                    <div class="sig-name">' . $contract->host->first_name . ' ' . $contract->host->last_name . '</div>
                    <div class="sig-role">صاحب العقار</div>
                </td>
                <td>
                    <div class="sig-name">' . $contract->tenant->first_name . ' ' . $contract->tenant->last_name . '</div>
                    <div class="sig-role">المستأجر</div>
                </td>
            </tr>
        </table>

        <div class="footer">
            تم إصدار هذا العقد إلكترونياً عبر منصة حمى للإيجار — hima.app<br>
            رقم العقد: #' . $contractNo . ' | تاريخ الإصدار: ' . $now . '
        </div>

        </body>
        </html>';

        // mPDF v8 API
        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'orientation'   => 'P',
            'margin_top'    => 10,
            'margin_bottom' => 10,
            'margin_left'   => 15,
            'margin_right'  => 15,
            'tempDir'       => storage_path('app/mpdf_temp'),
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont   = true;
        $mpdf->WriteHTML($html);

        // Save file
        $filename = 'contracts/contract_' . $contract->id . '_' . time() . '.pdf';
        $fullPath = storage_path('app/public/' . $filename);

        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        if (!file_exists(storage_path('app/mpdf_temp'))) {
            mkdir(storage_path('app/mpdf_temp'), 0755, true);
        }

        $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);

        return $filename;
    }
}