<?php

namespace Immersioninteractive\GenericController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use IIResponse;

class IIController extends Controller
{
    public function store(Request $request, $model, $current_user_fk, $parent_counter = null)
    {
        $data = $request;
        if ($current_user_fk != null) {
            $data = $request->all() + [$current_user_fk => Auth::User()->id];
        }

        if (!$object = $model::create($data)) {
            IIResponse::set_errors("Error creando el registro");
            IIResponse::response();
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

                return ResponseController::response();
            }
        }

        IIResponse::set_data(['id' => $object->id]);
        IIResponse::set_status_code('CREATED');

        return IIResponse::response();
    }

    public function get($model, $id = null, $pagination = null)
    {
        if ($id != null) {

            $table = $model->getTable();

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
                IIResponse::set_errors($validator->errors()->toArray());
                IIResponse::set_status_code('BAD REQUEST');
                return IIResponse::response();
            }

            $object = $model::find($id);
            $relations = $model::relation_names();
            $object['relations'] = $relations;
            foreach ($relations as $relation_name) {
                $object[$relation_name] = $object->$relation_name;
            }

            IIResponse::set_data($object);
            return IIResponse::response();
        }

        if ($pagination != null) {
            $object = $model::paginate($pagination);
        } else {
            $oobject = $model::all();
        }

        IIResponse::set_data($object);

        return IIResponse::response();
    }

    public function update(Request $request, $model, $request_exceptions_array = [])
    {
        try {
            $object = $model::where('id', $request->id);
            $object->update($request->except($request_exceptions_array));
        } catch (\Exception $e) {
            ResponseController::set_errors(true);
            IIResponse::set_errors("error actualizando el registro");
            IIResponse::set_errors($e->getMessage());
            IIResponse::set_status_code('BAD REQUEST');
            return IIResponse::response();
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
}
