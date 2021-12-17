<?php

namespace Botble\Member\Http\Requests;

use Botble\Support\Http\Requests\Request;
use RvMedia;

class MemberChangeAvatarRequest extends Request
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'avatar' => RvMedia::imageValidationRule(),
        ];
    }
}
