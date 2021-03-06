<?php

namespace App\Http\Controllers\api\uploads;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Vehicle_photos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class VehicleUploadController extends Controller
{
    protected $user;

    public function __construct()
    {
        $this->user = Auth()->guard('api')->user();
    }

    public function create(Request $request)
    {
        $file = $request->file('file');
        $fileName = md5(uniqid(time())) . strrchr($file->getClientOriginalName(), '.'); // criar nome aleatório para as imagens(md5) e também o formato da imagem
        $vehicle = Vehicle::where('user_id', $this->user->id) //fazer verificação se o veículo existe no banco
            ->find($request->id);

        if (!$vehicle) {
            return response()->json(['error' => 'Veículo não encontrado']);
        }

        if ($request->hasFile('file') && $file->isValid()) {
            $photo = Vehicle_photos::create([
                'user_id' => $this->user->id,
                'vehicle_id' => $request->id,
                'img' => $fileName
            ]);
            //verificar se cadastrou com sucesso
            if ($photo->id) {
                //usar biblioteca image intervention para reduzir o tamanho das imagens.
                //e também da image caching mais pra frente quando for pegá-las do db;

                $img = Image::make($request->file)->orientate();
                $img->resize(1000, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                Storage::put('vehicles/' . $this->user->id . '/' . $photo->vehicle_id . '/' . $fileName, $img->encode(), 'public');

                return $photo;
            }

            return response()->json(['error' => 'Erro ao cadastrar imagem']);
        }
    }

    public function update(Request $request)
    {
        foreach ($request->order as $order => $id) {
            $position = Vehicle_photos::where('user_id', $this->user->id->find($id));
            $position->order = $order;
            $position->save();
        }

        return response()->json(['success' => 'Posições atualizadas com sucesso']);
    }

    public function destroy($id)
    {
        $photo = Vehicle_photos::where('user_id', $this->user->id)->find($id);
        if ($photo->id) {
            $path = 'vehicles/' . $this->user->id . '/' . $photo->vehicle_id . '/' . $photo->img; //caminho da img
            if (Storage::exists($path)) {
                Storage::delete($path);
            }

            if ($photo->delete()) {
                return response()->json(['success' => 'Imagem apagada com sucesso']);
            }

            return response()->json(['error' => 'Error ao apagar imagem']);
        }
        return response()->json(['error' => 'Imagem não encontrada']);
    }
}
