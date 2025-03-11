<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DB;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
// use PhpOffice\PhpSpreadsheet\IOFactory;


class TempDeviceController extends AdminController
{

    /**
     * 注册列表
     */
    public function getRegisterList(Request $request)
    {
        $data = $this->getTempDeviceData($request);

        $result = $data->paginate($request->input('perpage', 20));
        return $this->presenter->json($result, "成功", 1);
    }

    /**
     * 注册列表 - 取消
     */
    public function cancelRegister(Request $request)
    {
        $this->validate($request, [
            'hashcode' => 'required'
        ]);
        $data = DB::table('temp_device')
            ->where('hashcode', $request->input('hashcode'))
            ->where('status', 0)
            ->first();
        if ($data) {
            $update = ['status' => -1];
            if ($data->burn_status == 0) {
                $update['burn_status'] = -1;
            }

            DB::table('temp_device')
                ->where('id', $data->id)
                ->where('status', 0)
                ->update($update);
        }


        return $this->presenter->json([], "成功", 1);
    }

    /**
     * 批量增加设备
     */
    public function multiAdd(Request $request)
    {
        $this->validate($request, [
            'number' => 'required|integer|between:1,100'
        ]);
        $number = $request->input('number');

        $hashcodeList = [];
        for ($i = 1; $i <= $number; $i++) {
            $hashcodeList[] = strtoupper(str_replace('-', '', Str::uuid()));
        }

        $fail_count = 0;
        // 删除重覆
        $hashcodeList = array_unique($hashcodeList);
        $fail_count += $number - count($hashcodeList);

        $usb_device = DB::table('usb_device')
            ->select(['hashcode'])
            ->whereIn('hashcode', $hashcodeList)
            ->get();
        if ($usb_device) {
            foreach ($usb_device as $val) {
                if (($key = array_search($val->hashcode, $hashcodeList)) !== false) {
                    unset($hashcodeList[$key]);
                    $fail_count++;
                }
            }
        }

        $temp_device = DB::table('temp_device')
            ->select(['hashcode'])
            ->whereIn('hashcode', $hashcodeList)
            ->where('status', '!=', -1)
            ->get();
        if ($temp_device) {
            foreach ($temp_device as $val) {
                if (($key = array_search($val->hashcode, $hashcodeList)) !== false) {
                    unset($hashcodeList[$key]);
                    $fail_count++;
                }
            }
        }

        $port_count = $request->input('port_count', 16);
        $insert_array = [];
        foreach ($hashcodeList as $hashcode) {
            $insert_array[] = [
                'hashcode' => $hashcode,
                'port_count' => $port_count,
                'status' => 0,
            ];
        }

        DB::table('temp_device')->insert($insert_array);

        $successCount = count($hashcodeList);
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
        // $resultExcel = IOFactory::load(resource_path('excel/empty_usbdevice.xls'));
        // $resultExcel->setActiveSheetIndex(0);
        $resultExcel = new Spreadsheet();
        $sheet = $resultExcel->getActiveSheet();
        $title = [
            '', 'sequence', 'hashcode', 'port数',
            '状态(-1:取消, 0:未新增, 1:已新增)',
            '列印状态(0:未列印, 1:已列印)',
            '烧录状态(-1: 作废, 0: 未烧录, 1:已烧录)',
            '建立时间',
        ];

        $sheet->fromArray($title, NULL, 'A1');
        $i = 2;
        $data = $this->getTempDeviceData($request);
        $count = $data->count();
        if ($count > 1000) {
            return view('jsalert', ['msg' => '资料笔数超过1000笔，请缩小搜寻范围再重试。']);
        } elseif ($count == 0) {
            return view('jsalert', ['msg' => '无资料。']);
        }

        $data = $data->get();
        $idList = [];
        foreach ($data as $row) {
            $idList[] = $row->id;
            $rowData = [
                '',
                str_pad($row->id, 8, "0", STR_PAD_LEFT),
                $row->hashcode,
                $row->port_count,
                strval($row->status),
                strval($row->print_status),
                strval($row->burn_status),
                $row->created_at,
            ];
            $sheet->fromArray($rowData, NULL, 'A'.$i);
            // 因WPS问题，ID必须以TYPE_STRING方式存入
            // $sheet->setCellValueExplicit('A'.$i, $rowData[0], DataType::TYPE_STRING);
            $i++;
        }

        $updateSplit = array_chunk($idList, 300);
        foreach ($updateSplit as $val) {
            // 更新资料库列印状态
            DB::table('temp_device')
               ->whereIn('id', $val)
               ->update(['print_status' => 1]);
        }

        // width
        foreach(range('A', 'F') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $writer = new Xls($resultExcel);
        $writer->save($resultFile);

        return response()->download($resultFile, date("YmdHis").'未出厂列表.xls');
    }

    private function getTempDeviceData($request)
    {
        $data = DB::table('temp_device')
            ->orderBy('created_at', 'asc');

        if ($request->filled('status')) {
            $data->where('status', $request->input('status'));
        }
        if ($request->filled('print_status')) {
            $data->where('print_status', $request->input('print_status'));
        }
        if ($request->filled('burn_status')) {
            $data->where('burn_status', $request->input('burn_status'));
        }

        if ($request->filled('hashcode')) {
            $data->where('hashcode', 'like', '%' . $request->input('hashcode') . '%');
        }

        if ($request->filled('id')) {
            $data->where('id', $request->input('id'));
        }

        if($request->filled('startDate')){
            $data->where('created_at', '>=', $request->input('startDate'));
        }
        if($request->filled('endDate')){
            $data->where('created_at', '<=', $request->input('endDate'));
        }
        return $data;
    }
}
