<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DB;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Libraries\Functions;

class TempUBoxController extends AdminController
{

    public function getList(Request $request)
    {
        $data = $this->getTempUBoxData($request);

        $result = $data->paginate($request->input('perpage', 20));
        return $this->presenter->json($result, "成功", 1);
    }

    /**
     * 批量增加ubox uid
     */
    public function multiAdd(Request $request)
    {
        $this->validate($request, [
            'number' => 'required|integer|between:1,100'
        ]);
        $number = $request->input('number');

        $startIndex = DB::transaction(function () use ($number) {
            $config = DB::table('config')
                ->where('name', 'temp_ubox_index')
                ->limit(1)
                ->lockForUpdate()
                ->first();

            if ($config) {
                $updated = DB::table('config')
                    ->where('name', 'temp_ubox_index')
                    ->increment('value', $number);
                return $config->value;
            }
            return 0;
        });

        if (!$startIndex) {
            return $this->presenter->json([], '失败，请配置config temp_ubox_index', 2);
        }

        $uidList = [];
        for ($i = 1; $i <= $number; $i++) {
            $uidList[] = $this->generateUid($startIndex);
            $startIndex++;
        }

        $fail_count = 0;
        // 删除重覆
        $uidList = array_unique($uidList);
        $fail_count += $number - count($uidList);

        $temp = DB::table('temp_ubox')
            ->select(['usb_uid'])
            ->whereIn('usb_uid', $uidList)
            ->get();

        if ($temp) {
            foreach ($temp as $val) {
                if (($key = array_search($val->usb_uid, $uidList)) !== false) {
                    unset($uidList[$key]);
                    $fail_count++;
                }
            }
        }

        $insert_array = [];
        foreach ($uidList as $uid) {
            $insert_array[] = [
                'usb_uid' => $uid,
            ];
        }

        DB::table('temp_ubox')->insert($insert_array);

        $successCount = count($uidList);
        $returnType = 1;
        $msg = sprintf("成功%s笔", $successCount);
        if ($fail_count) {
            $msg .= sprintf(', 失败%s笔, 请重新创建！', $fail_count);
            $returnType = 2;
        }

        return $this->presenter->json([], $msg, $returnType);

    }

    public function exportData(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $resultFile = tempnam(sys_get_temp_dir(), 'TransactionExportData');
        $resultExcel = new Spreadsheet();
        $sheet = $resultExcel->getActiveSheet();
        $title = [
            '', 'sequence', 'uid',
            '烧录状态(-1: 作废, 0: 未烧录, 1:已烧录)',
            '列印状态(0:未列印, 1:已列印)',
            '建立时间', '最后更新时间',
        ];

        $sheet->fromArray($title, NULL, 'A1');
        $i = 2;
        $data = $this->getTempUBoxData($request);
        $count = $data->count();
        if ($count > 3000) {
            return view('jsalert', ['msg' => '资料笔数超过3000笔，请缩小搜寻范围再重试。']);
        } elseif ($count == 0) {
            return view('jsalert', ['msg' => '无资料。']);
        }

        $data = $data->get();
        $idList = [];
        foreach ($data as $row) {
            $idList[] = $row->id;
            $rowData = [
                '',
                // str_pad($row->id, 8, "0", STR_PAD_LEFT),
                substr($row->usb_uid, 0, 10),
                $row->usb_uid,
                strval($row->burn_status),
                strval($row->print_status),
                $row->created_at,
                $row->updated_at,
            ];
            $sheet->fromArray($rowData, NULL, 'A'.$i);
            $i++;
        }

        $updateSplit = array_chunk($idList, 300);
        foreach ($updateSplit as $val) {
            // 更新资料库列印状态
            DB::table('temp_ubox')
               ->whereIn('id', $val)
               ->update(['print_status' => 1]);
        }

        // width
        foreach(range('A', 'E') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $writer = new Xls($resultExcel);
        $writer->save($resultFile);

        return response()->download($resultFile, date("YmdHis").'U盒列表.xls');
    }

    private function getTempUBoxData($request)
    {
        $data = DB::table('temp_ubox')
            ->orderBy('id', 'desc');

        if ($request->filled('burn_status')) {
            $data->where('burn_status', $request->input('burn_status'));
        }
        if ($request->filled('print_status')) {
            $data->where('print_status', $request->input('print_status'));
        }
        if ($request->filled('usb_uid')) {
            $data->where('usb_uid', 'like', '%' . $request->input('usb_uid') . '%');
        }
        if ($request->filled('id')) {
            $data->where('id', $request->input('id'));
        }

        return $data;
    }

    private function generateUid($index)
    {
        // A+9 + 13 + 3 (流水号 + 随机码 + 检查码)
        // $index = str_pad(sprintf('%X', $index), 8, "0", STR_PAD_LEFT);
        $index = 'A' . str_pad(sprintf('%u', $index), 9, "0", STR_PAD_LEFT);
        $uuid = str_replace("-", '', (string) Str::uuid());
        $random = substr($uuid, -13);

        $uid = $index . $random;
        $code = Functions::calcUidCode($uid);

        return strtoupper($uid . $code);
    }
}
