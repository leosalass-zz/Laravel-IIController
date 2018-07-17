<?php

namespace Immersioninteractive\GenericController;

use App\Http\Controllers\Controller;
use IIResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Immersioninteractive\ToolsController\IITools;
use Validator;

class IIController extends Controller
{
    public function store($request, $model, $current_user_fk = null, $parent_counter = null, $return_only_id = false)
    {
        $data = $request->toArray();
        if ($current_user_fk != null) {
            $data = $request->all() + [$current_user_fk => Auth::id()];
        }

        if (!$object = $model::create($data)) {
            IIResponse::set_errors("Error creando el registro");
            return IIResponse::response();
        }

        if ($parent_counter != null) {
            try {
                $object->parent->$parent_counter++;
                $object->parent->save();
            } catch (\Exception $e) {

                $model::destroy($object->id);

                IIResponse::set_errors("error sumando el contador, el registro ha sido eliminado");
                IIResponse::set_errors($e->getMessage());
                IIResponse::set_status_code('BAD REQUEST');

                return IIResponse::response();
            }
        }

        /*
         * base64_image[TABLE_FIELD_NAME][] = BASE64_IMAGE_FORMAT
         */
        if ($request->exists('base64_image')) {

            $this->base64_image($request, $model, $object);
        }

        $response = $object;

        if($return_only_id){
            $response = ['id' => $object->id];
        }

        IIResponse::set_data($response);
        IIResponse::set_status_code('CREATED');

        return IIResponse::response();
    }

    public function get($model, $id = null, $pagination = null, $unset_array = null)
    {
        if ($id != null) {

            $table = with(new $model)->getTable();

            $validator = Validator::make(['id' => $id], [
                'id' =>
                [
                    'required',
                    Rule::exists($table, 'id')->where(function ($query) {
                        $query->where('deleted_at', null);
                    }),
                ],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $error) {
                    IIResponse::set_errors($error[0]);
                }
                IIResponse::set_status_code('BAD REQUEST');
                return IIResponse::response();
            }

            $object = $model::find($id);

            try {
                /**
                 * Custom Model method that returns an array with relation names
                 */
                $relations = $model::relation_names();

                $object['relations'] = $relations;
                foreach ($relations as $relation_name) {
                    $object[$relation_name] = $object->$relation_name;
                }
            } catch (\Exception $e) {

            }

            if ($unset_array != null) {
                foreach ($unset_array as $item) {
                    unset($object->$item);
                }
            }

            IIResponse::set_data($object);
            return IIResponse::response();
        }

        if ($pagination != null) {
            $objects = $model::paginate($pagination);
        } else {
            $objects = $model::all();
        }

        if ($unset_array != null) {
            foreach ($objects as $object) {
                foreach ($unset_array as $item) {
                    unset($object->$item);
                }
            }
        }

        IIResponse::set_data($objects);

        return IIResponse::response();
    }

    public function get_child($model, $id, $relation_name){
        /**
         * to make this function work:
         * 1- a has many relation must be set in the model of this controller
         * 2- the relation must has the name of the related model table
         */
        $table = with(new $model)->getTable();                
        
        $validator = Validator::make(['id' => $id], [
            'id' => "required|integer|min:1|exists:$table,id",
        ]);

        if ($validator->fails()) {
            foreach($validator->errors()->toArray() as $index => $e){
                IIResponse::set_errors($e[0]);
            }
            
            return IIResponse::response();
        }    
        
        $object = $model::find($id);
        
        if ($object->$relation_name) {            
            return IIResponse::response($object->$relation_name);
        }else{            
            IIResponse::set_errors("the child does not exists");
            return IIResponse::response();
        }

    }

    public function update(Request $request, $model, $request_exceptions_array = [])
    {
        try {
            $object = $model::where('id', $request->id)->first();
            $object->update($request->except($request_exceptions_array));
            IIResponse::set_data(['id' => $request->id]);
        } catch (\Exception $e) {
            IIResponse::set_errors("error actualizando el registro");
            IIResponse::set_errors($e->getMessage());
            IIResponse::set_status_code('BAD REQUEST');
            return IIResponse::response();
        }

        /*
         * base64_image[TABLE_FIELD_NAME][] = BASE64_IMAGE_FORMAT
         */
        if ($request->exists('base64_image')) {

            $this->base64_image($request, $model, $object, true);
        }

        IIResponse::set_status_code('OK');
        return IIResponse::response();
    }

    public function destroy($model, $id, $parent_counter = null)
    {
        if ($parent_counter != null) {
            try {
                $object = $model::find($id);
                $object->parent->$parent_counter--;
                $object->parent->save();
                IIResponse::set_data(['id' => $id]);
            } catch (\Exception $e) {
                IIResponse::set_errors("error restando el contador, el registro no ha sido eliminado");
                IIResponse::set_errors($e->getMessage());
                IIResponse::set_status_code('BAD REQUEST');
                return IIResponse::response();
            }
        }

        try {
            $model::destroy($id);
        } catch (\Exception $e) {
            IIResponse::set_errors("error eliminado el registro");
            IIResponse::set_errors($e->getMessage());
            IIResponse::set_status_code('BAD REQUEST');
            return IIResponse::response();
        }

        IIResponse::set_status_code('OK');
        return IIResponse::response();
    }

    public function base64_image($request, $model, $object, $remove_images = false)
    {
        $keys = array_keys($request->base64_image);

        try {
            $model_name_array = explode('\\', $model);
            $model_name = strtolower($model_name_array[1]);
            $directory_path = "$model_name/id/$object->id";
            $extension = 'jpg';
        } catch (\Exception $e) {}

        foreach ($keys as $field_name) {

            $directory_path .= DIRECTORY_SEPARATOR . $field_name;

            $file_name = date("Ymdhis") . rand(11111, 99999);
            $full_name = "$file_name.$extension";
            $url = URL::to('/') . DIRECTORY_SEPARATOR . IITools::$base_image_path . $directory_path . DIRECTORY_SEPARATOR . $full_name;
            $url_field = $field_name . '_url';
            
            if ($remove_images && $object->$field_name != null) {
                $file = public_path(DIRECTORY_SEPARATOR . IITools::$base_image_path . $directory_path . DIRECTORY_SEPARATOR . $object->$field_name);
                try {
                    unlink($file);
                } catch (\Exception $e) {}
                }
                
                foreach ($request->base64_image[$field_name] as $base64_image) {
                    
                    if($model_name == 'user' && $field_name == 'image'){
                        $full_name = "user.$extension";
                        $url = URL::to('/') . DIRECTORY_SEPARATOR . IITools::$base_image_path . $directory_path . DIRECTORY_SEPARATOR . $full_name;
                    }                

                IITools::base64_to_file($base64_image, $full_name, $directory_path);
                $object->$field_name = $full_name;
                $object->$url_field = $url;
                $object->save();

                $request['image'] = "$file_name.$extension";

            }
        }
    }
}
