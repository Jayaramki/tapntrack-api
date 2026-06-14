<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSettingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
        ]);

        $settings = AppSetting::where('book_id', $data['book_id'])
            ->orderBy('key')
            ->get();

        return $this->success($settings);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $setting = AppSetting::updateOrCreate(
            [
                'book_id' => $request->input('book_id'),
                'key' => $request->input('key'),
            ],
            [
                'value' => $request->input('value'),
                'updated_by' => auth()->id(),
            ]
        );

        return $this->success($setting, 'Setting updated successfully');
    }
}
