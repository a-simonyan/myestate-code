<?php

namespace App\GraphQL\Queries;
use App\UserFavoriteProperty;
use Auth;
use App\Http\Traits\GetIdTrait;
use App\Http\Traits\ChangeCurrencyTrait;
use App\CurrencyType;
use App\PropertyType;
use App\DealType;

class UserFavoriteProperties
{
    use GetIdTrait, ChangeCurrencyTrait;
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $user_auth   = Auth::user();
        $user_id     = $user_auth->id;

        $first = !empty($args['first']) ? $args['first'] : 10;
        $page  = !empty($args['page']) ? $args['page'] : 1;

        $field = 'id';
        // ASC or DESC
        $order = 'DESC';

        if(!empty($args['orderBy'])){
            $field = $args['orderBy']['field'];
            $order = $args['orderBy']['order'];
        };

        $userFavoritePropertiesClass = UserFavoriteProperty::with('property')
        ->whereHas('property' , function ($query) {
            $query->whereNull('deleted_at');
            $query->whereNull('saved_at');
        })
        ->where('user_id',$user_id);

            if(!empty($args['property_type'])){
                $typeArr=[];
                foreach($args['property_type'] as $property_type){
                   $property_type_id = $this->getKeyId(PropertyType::Class,'name',$property_type);
                   array_push($typeArr,$property_type_id);
                }

                $userFavoritePropertiesClass=$userFavoritePropertiesClass->whereHas('property' , function ($query) use ( $typeArr){
                       $query->whereIn('property_type_id',$typeArr);
                });

            }
              

            /*order by price  ASC and DESC*/
          if(!empty($args['price_order'])){

            $userFavoriteProperties = $userFavoritePropertiesClass->get();


            $currency_type_id = CurrencyType::where('is_current',true)->first()->id;
            $propertie_plus = [];
            $propertie_minus = [];
            $price_order=$args['price_order'];


            $deal_type_id=$this->getKeyId(DealType::Class,'name','sale');
            foreach($userFavoriteProperties as $userFavoritePropertie){
                $propertie=$userFavoritePropertie->property;

                foreach($propertie->property_deals as $property_deal){
                    if($property_deal->deal_type_id==$deal_type_id){
                        $userFavoritePropertie->price_order = $this->changeCurrency($property_deal->price,$property_deal->currency_type_id,$currency_type_id);
                        break;
                    }
                }

                if(empty($userFavoritePropertie->price_order) && !empty($propertie->property_deals[0])){
                    $property_deal = $propertie->property_deals[0];
                    $userFavoritePropertie->price_order = -$this->changeCurrency($property_deal->price,$property_deal->currency_type_id,$currency_type_id);

                    $propertie_minus[]=$userFavoritePropertie;

                } else {
                    $propertie_plus[]=$userFavoritePropertie;
                }


            }

            $propertie_plus  = collect($propertie_plus);
            $propertie_minus = collect($propertie_minus);

            if( $price_order == 'DESC') {
                $propertie_plus  = $propertie_plus->sortByDesc('price_order');
                $propertie_minus = $propertie_minus->sortBy('price_order'); 
                $userFavoriteProperties = $propertie_plus->merge($propertie_minus);
            } else {
               $propertie_plus  = $propertie_plus->sortBy('price_order');
               $propertie_minus = $propertie_minus->sortByDesc('price_order'); 
               $userFavoriteProperties = $propertie_plus->merge($propertie_minus);
            }

            return $userFavoriteProperties->forPage($page, $first);


          } else {

             $userFavoriteProperties = $userFavoritePropertiesClass->orderBy($field, $order)
             ->paginate($first,['*'],'page', $page);
          }

        return $userFavoriteProperties;


    }
}
