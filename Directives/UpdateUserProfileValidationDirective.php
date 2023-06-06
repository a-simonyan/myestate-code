<?php

namespace App\GraphQL\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;


class UpdateUserProfileValidationDirective extends ValidationDirective 
{
    public function rules(): array
    {
        
        return [
            'full_name'    => ['string','nullable' ],
            'email'        => ['email', 'unique:users','nullable'],
            'password'     => ['min:8','nullable' ],
            'password_confirmation' => ['same:password','nullable'],
        ];
    }

    public function messages(): array
    {   
        
        return [
            'password_confirmation.same' =>  __('messages.same_password_validation'),
        ];
    }

}
