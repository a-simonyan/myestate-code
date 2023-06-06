<?php

namespace App\GraphQL\Directives;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Schema\Directives\ValidationDirective;

class CreatePropertyValidationDirective extends ValidationDirective 
{
    public function rules(): array
    {
        
        return [
            'property_type'     => ['required','string','in:apartment,mansion,land_area,commercial_area'],
            'bulding_type_id'   => [],
            'land_area_type_id' => [],
            'latitude'          => ['required','numeric'],
            'longitude'         => ['required','numeric'],
            'address'           => ['required','string'],
            'property_images.*' => ['max:10240','nullable'],
            'property_filter_values' => ['array','nullable'],
            'property_filter_values.*.filter' => [function ($attribute, $value, $fail) {
                $arr = explode('.', $attribute);
                $index = $arr[1];
                $filterValue = $this->args['property_filter_values'][$index]['value'];
                if($value == 'property_height'||$value =='number_of_bathrooms'||$value == 'area'||$value == 'number_of_floors_of_the_building'||$value == 'apartment_floor'||$value == 'number_of_rooms'||$value == 'land_area'){
                    if(!is_null($filterValue) && !is_numeric($filterValue)){
                        $fail(__($value.' filter value wrong'));
                    }
                } else {
                    if(!is_null($filterValue)&&($filterValue !== 'true') && ($filterValue !== 'false') ){
                        $fail(__($value.' filter value wrong'));
                    }
                }
              
            }],
            'property_deal_types' => ['array','nullable'],
            'property_deal_types.*.price' => [function ($attribute, $value, $fail) {
                $arr = explode('.', $attribute);
                $index = $arr[1];
                $filterValue = $this->args['property_deal_types'][$index]['currency_type_id'];
                if(!is_null($filterValue)&&is_null($value)){
                    $fail(__('price can not be empty'));
                }
             }],
             'property_deal_types.*.currency_type_id' => [function ($attribute, $value, $fail) {
                $arr = explode('.', $attribute);
                $index = $arr[1];
                $filterValue = $this->args['property_deal_types'][$index]['price'];
                if(!is_null($filterValue)&&is_null($value)){
                    $fail(__('currency type can not be empty'));
                }
             }],
        ];
    }

}
