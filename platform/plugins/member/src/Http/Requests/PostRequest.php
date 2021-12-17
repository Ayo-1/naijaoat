<?php

namespace Botble\Member\Http\Requests;

use Botble\Blog\Http\Requests\PostRequest as BasePostRequest;
use Illuminate\Support\Arr;
use RvMedia;

class PostRequest extends BasePostRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $imageRule = RvMedia::imageValidationRule();

        if (is_string($imageRule)) {
            $imageRule = str_replace('required|', '', $imageRule);
        } elseif (is_array($imageRule)) {
            Arr::forget($imageRule, 'required');
        }

        return parent::rules() + ['image_input' => $imageRule];
    }
}
