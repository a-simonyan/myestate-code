<?php

namespace App\GraphQL\Mutations;
use App\DealType;
use App\Filter;
use App\Property;
use App\PropertyImage;
use App\PropertyType;
use App\FiltersValue;
use App\TranslateDescription;
use App\PropertyDeal;
use App\Language;
use App\PropertyAttachPhone;
use App\TranslatePropertyAddress;
use Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Http\Traits\GetIdTrait;
use Image;
use Illuminate\Support\Facades\Http;
use App\Http\Services\PropertyService;
use Carbon\Carbon;
use App\Events\AdminPropertyNotification;

class CreateProperty
{
   

    use GetIdTrait;
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {

        $user_auth   = Auth::user();
        $user_id     = $user_auth->id;
        $user_type   = $user_auth->user_type->name;

        if(empty($args['property_id'])){

            $property = Property::create(['property_key'       => !empty($args['property_key'])? $args['property_key'] : null,
                                          'property_type_id'   => $this->getKeyId(PropertyType::Class,'name',$args['property_type']),
                                          'user_id'            => $user_id,  
                                          'bulding_type_id'    => !empty($args['bulding_type_id'])? $args['bulding_type_id']:null,
                                          'land_area_type_id'  => !empty($args['land_area_type_id'])? $args['land_area_type_id']:null,
                                          'latitude'           => $args['latitude'],
                                          'longitude'          => $args['longitude'],
                                          'address'            => $args['address'],
                                          'region'             => $args['region'],
                                          'postal_code'        => !empty($args['postal_code'])? $args['postal_code'] : null,
                                          'property_state'     => !empty($args['property_state'])? $args['property_state'] : null,
                                          'email'              => ( $user_type == 'agency' && !empty($args['email'])) ?  $args['email'] : null,
                                          'is_bids'            => ( $user_type == 'agency' && isset($args['is_bids'])) ? $args['is_bids'] : false,     
                                          'is_address_precise' => ( $user_type == 'agency' && isset($args['is_address_precise'])) ?  $args['is_address_precise'] : true,
                                          'last_update'        => Carbon::now()
                                         ]); 
    
            if($property){                             
                                      
                 $property_id=$property->id;
    
                 if(!empty($args['property_deal_types'])){
                   $this->savePropertyDealTypes($property_id, $args['property_deal_types']);
                 }
                 if(!empty($args['property_images'])){
                   $this->savePropertyImages($property_id,$args['property_images']);
                 }
                 if(!empty($args['phone'])){
                    $this->savePhone($user_auth,$property_id,$args['phone']);
                  }
                 if(!empty($args['property_filter_values'])){
                   $this->savePropertyFilterValues($property_id, $this->getKeyId(PropertyType::Class,'name',$args['property_type']), $args['property_filter_values']);
                 }
                 if(!empty($args['translate_descriptions'])){
                   $this->saveTranslateDescription($property_id, $args['translate_descriptions']);
                 }
                 if(!empty($args['longitude'])&&!empty($args['latitude'])){
                    PropertyService::getAndSaveTranslatePropertyAddress($property_id, $args['longitude'].','.$args['latitude']);
                 }
               
    
            }

        } else {

            $property = Property::find($args['property_id']);

            if($property){
                $property->update(
                   ['property_key'       => !empty($args['property_key'])? $args['property_key'] : null,
                    'property_type_id'   => $this->getKeyId(PropertyType::Class,'name',$args['property_type']),
                    'user_id'            => $user_id,  
                    'bulding_type_id'    => !empty($args['bulding_type_id'])? $args['bulding_type_id']:null,
                    'land_area_type_id'  => !empty($args['land_area_type_id'])? $args['land_area_type_id']:null,
                    'latitude'           => $args['latitude'],
                    'longitude'          => $args['longitude'],
                    'address'            => $args['address'],
                    'region'             => $args['region'],
                    'postal_code'        => !empty($args['postal_code'])? $args['postal_code'] : null,
                    'property_state'     => !empty($args['property_state'])? $args['property_state'] : null,
                    'email'              => ( $user_type == 'agency' && !empty($args['email']))?  $args['email'] : null,
                    'is_bids'            => ( $user_type == 'agency' && isset($args['is_bids'])) ? $args['is_bids'] : false,
                    'is_address_precise' => ( $user_type == 'agency' && isset($args['is_address_precise'])) ?  $args['is_address_precise'] : true,
                    'saved_at'           => null,
                    'last_update'        => Carbon::now()
                   ]
                );

            }


            $property_id=$property->id;
    
            if(!empty($args['property_deal_types'])){
                PropertyDeal::where('property_id',$property_id)->delete();  
                $this->savePropertyDealTypes($property_id, $args['property_deal_types']);
            }
            if(!empty($args['property_images'])){
                $this->savePropertyImages($property_id,$args['property_images']);
            }
            if(!empty($args['phone'])){
                PropertyAttachPhone::where('property_id',$property_id)->delete();
                $this->savePhone($user_auth,$property_id,$args['phone']);
              }
            if(!empty($args['property_filter_values'])){
                FiltersValue::where('property_id',$property_id)->delete();
                $this->savePropertyFilterValues($property_id, $this->getKeyId(PropertyType::Class,'name',$args['property_type']), $args['property_filter_values']);
            }
            if(!empty($args['translate_descriptions'])){
                TranslateDescription::where('property_id',$property_id)->delete(); 
                $this->saveTranslateDescription($property_id, $args['translate_descriptions']);
            };
            if(!empty($args['property_images_delete_ids'])){
                $this->deletePropertyImages($user_auth, $args['property_images_delete_ids']);
            }
            if(!empty($args['longitude'])&&!empty($args['latitude'])){
                PropertyService::getAndSaveTranslatePropertyAddress($property_id, $args['longitude'].','.$args['latitude']);
            }


        }

        //Property during 22:00 to 8:00 must be automatically published.
        $now = Carbon::now();
        $start = Carbon::createFromTimeString('22:00');
        $end = Carbon::createFromTimeString('08:00')->addDay();

        if ($now->between($start, $end)) {
            $property->update(['is_public_status' => 'published']);
        }

        event( new AdminPropertyNotification(['property'=>$property->fresh()]) );

        return  $property;
    }

    public function savePropertyDealTypes($property_id, $property_deal_types){

         foreach($property_deal_types as $property_deal_type){
            PropertyDeal::create([
                'property_id'       => $property_id,
                'deal_type_id'      => $this->getKeyId(DealType::Class,'name',$property_deal_type['deal_type']) ,
                'price'             => $property_deal_type['price'],
                'currency_type_id'  => $property_deal_type['currency_type_id'],
            ]);
         }

    }



    public function savePropertyImages($property_id,$property_images){

      if($property_images){

        $propertyImage=PropertyImage::where('property_id',$property_id)->orderBy('index','desc')->first();

        if($propertyImage){
            $index = $propertyImage->index + 1;
        } else {
            $index = 1;
        }

        foreach($property_images as $property_image){
                
                $fileName_img = Str::random(10).time().'.'.$property_image->getClientOriginalExtension();
                while(file_exists(storage_path('app/public/property/'.$fileName_img))){
                    $fileName_img = Str::random(10).time().'.'.$property_image->getClientOriginalExtension();
                };
                $property_image->storeAs('public/property',$fileName_img);
                if(file_exists(storage_path('app/public/property/'.$fileName_img))){

                    $image = Image::make(storage_path('app/public/property/'.$fileName_img));

                    $image->resize(null, 200, function($constraint) {
                        $constraint->aspectRatio();
                    });
            
                    $image->save(storage_path('app/public/property/min/'.$fileName_img));
    

                    PropertyImage::create([
                        'property_id' => $property_id,
                        'name'        => $fileName_img,
                        'index'       => $index++
                    ]);
                }
        }
        
      }
        return true;

    }


    public function deletePropertyImages($user_auth, $property_images_delete_ids){

        foreach($property_images_delete_ids as $images_id){
           $propertyImage=PropertyImage::find($images_id);
           if($propertyImage && $user_auth->id == $propertyImage->property->user_id){
               $propertyImage_name = $propertyImage->getRawOriginal('name');

               if($propertyImage_name&&file_exists(storage_path('app/public/property/'.$propertyImage_name))){
                   unlink(storage_path('app/public/property/'.$propertyImage_name));
                 }
               if($propertyImage_name&&file_exists(storage_path('app/public/property/min/'.$propertyImage_name))){
                    unlink(storage_path('app/public/property/min/'.$propertyImage_name));
                }  
                 $propertyImage->delete();
           }
   
        }
   
       return true;
   
    }










    public function savePropertyFilterValues($property_id, $property_type_id, $property_filter_values){

        $property_type_filters = PropertyType::find($property_type_id)->filters;
    
    
        if($property_type_filters){
            foreach($property_type_filters as $property_type_filter){
                FiltersValue::create([
                    'filter_id'   => $property_type_filter->id,
                    'property_id' => $property_id
                ]);
            }
        }
        if($property_filter_values){
           foreach($property_filter_values as $property_filter_value){
               
               $deal_type = !empty($property_filter_value['deal_type']) ? $property_filter_value['deal_type'] : null;
               $filter = Filter::where('name', $property_filter_value['filter'])
                                 ->where('deal_type',  $deal_type)
                                 ->first();
               if($filter){
                    $filter_id = $filter->id;
                    FiltersValue::where('filter_id',$filter_id)->where('property_id',$property_id)
                                ->update(['value' => !empty($property_filter_value['value']) ? $property_filter_value['value'] : NULL ]);
               }                  
           }
        }
         
        return true;

    }

    public function saveTranslateDescription($property_id,$translate_descriptions){

         if($translate_descriptions){
            TranslateDescription::where('property_id', $property_id)->delete();

            foreach($translate_descriptions as $translate_description){
                $language_code = $translate_description['language'];
                $language = Language::where('code',$language_code)->first();
                if($language){
                    $language_id = $language->id;
                    TranslateDescription::create([
                      'property_id' => $property_id,
                      'language_id' => $language_id,
                      'description' => $translate_description['description']
                    ]);

                }

            }


         }

         return true;


    }

    public function savePhone($user_auth,$property_id,$phone){
         if(!empty($phone['attach_phones'])){

             foreach($phone['attach_phones'] as $key){
                $userPhones = $user_auth->phones;
                $attachPhone = $userPhones->where('id',$key)->first();
                if($attachPhone){
                    PropertyAttachPhone::create([
                        'code'        => $attachPhone->code,
                        'number'      => $attachPhone->number,
                        'viber'       => $attachPhone->viber,
                        'whatsapp'    => $attachPhone->whatsapp,
                        'telegram'    => $attachPhone->telegram,
                        'property_id' => $property_id
                    ]);
                }
             }
         }
         if(!empty($phone['new_phones'])){
            foreach($phone['new_phones'] as $newPhone){
                PropertyAttachPhone::create([
                    'code'        => $newPhone['code'],
                    'number'      => $newPhone['number'],
                    'viber'       => !empty($newPhone['viber']) ? $newPhone['viber'] : false,
                    'whatsapp'    => !empty($newPhone['whatsapp']) ? $newPhone['whatsapp'] : false,
                    'telegram'    => !empty($newPhone['telegram']) ? $newPhone['telegram'] : false,
                    'property_id' => $property_id
                ]);
            }


         }

         return true;

    }



}
