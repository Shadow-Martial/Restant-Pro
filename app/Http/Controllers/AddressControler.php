<?php

namespace App\Http\Controllers;

use App\Address;
use App\Restorant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddressControler extends Controller
{
    protected $autocomepleteEndpoint = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    protected $detailsEndpoint = 'https://maps.googleapis.com/maps/api/place/details/json';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (auth()->user()->hasRole('client')) {
            $addresses = Address::where(['user_id' => auth()->user()->id])->where(['active' => 1])->get();

            return view('addresses.index', ['addresses' => $addresses]);
        } else {
            return redirect()->route('orders.index')->withStatus(__('No Access'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $address = new Address;
        $address->address = strip_tags($request->new_address);
        $address->user_id = auth()->user()->id;
        $address->lat = $request->lat;
        $address->lng = $request->lng;
        $address->apartment = $request->apartment ?? $request->apartment;
        $address->intercom = $request->intercom ?? $request->intercom;
        $address->floor = $request->floor ?? $request->floor;
        $address->entry = $request->entry ?? $request->entry;
        $address->save();

        return response()->json([
            'status' => true,
            'success_url' => redirect()->intended('/cart-checkout')->getTargetUrl(),
            'msg' => 'Address successfully added!',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Address $address)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Address $address)
    {
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        $address->lat = $request->lat;
        $address->lng = $request->lng;

        $address->update();

        return response()->json([
            'status' => true,
            'success_url' => redirect()->intended('/cart-checkout')->getTargetUrl(),
            'msg' => 'Address successfully updated!',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address): RedirectResponse
    {
        $address->active = 0;
        $address->save();

        return redirect()->route('addresses.index')->withStatus(__('Address successfully deactivated.'));
    }

    public function orderAddress(Request $request): JsonResponse
    {
        $address = Address::where(['id' => $request->addressID])->get()->first();

        return response()->json([
            'status' => true,
            'data' => [
                'lat' => $address->lat,
                'lng' => $address->lng,
                'address' => $address->address,
            ],
            'msg' => '',
        ]);
    }

    public function newAddressAutocomplete(): JsonResponse
    {
        if (! isset($_GET['term'])) {
            return response()->json(['results' => []]);
        }

        $term = $_GET['term'];

        if (strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $client = new \GuzzleHttp\Client();

        $payload = [
            'query' => [
                'key' => config('settings.google_maps_api_key'),
                'input' => $term,
            ],
        ];
        $response = $client->request('GET', $this->autocomepleteEndpoint, $payload);

        if ($response->getStatusCode() !== 200) {
            return response()->json(['results' => []]);
        }

        $responseDecoded = json_decode($response->getBody());

        $matches = [];
        if (isset($responseDecoded->predictions)) {
            foreach ($responseDecoded->predictions as $key => $prediction) {
                array_push($matches, ['id' => $prediction->place_id, 'text' => $prediction->description]);
            }
        }

        $data = ['results' => $matches];

        return response()->json($data);
    }

    public function newAdressPlaceDetails(Request $request): JsonResponse
    {
        $itemToReturn = null;
        $client = new \GuzzleHttp\Client();

        $payload = [
            'query' => [
                'key' => config('settings.google_maps_api_key'),
                'place_id' => $request->place_id,
            ],
        ];

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $this->detailsEndpoint, $payload);

        if ($response->getStatusCode() !== 200) {
            return response()->json([
                'status' => false,
                'result' => [],
            ]);
        }

        $responseDecoded = json_decode($response->getBody());

        if (isset($responseDecoded->result)) {
            $itemToReturn['google_place_id'] = $request->place_id;

            //Find the lat and lng
            $itemToReturn['lat'] = $responseDecoded->result->geometry->location->lat;
            $itemToReturn['lng'] = $responseDecoded->result->geometry->location->lng;

            //name
            $itemToReturn['name'] = $responseDecoded->result->name;

            return response()->json([
                'status' => true,
                'result' => $itemToReturn,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'result' => [],
            ]);
        }
    }

    public function AddressInDeliveryArea(Request $request): JsonResponse
    {
        $restaurant_id = $request->restaurant_id;
        $ids = $request->address_ids;
        $positions = [];

        $restorant = Restorant::select('radius')->where(['id' => $restaurant_id])->get()->first();

        foreach ($ids as $id) {
            $address = Address::select('lat', 'lng')->where(['id' => $id])->get()->first();

            $address_obj = (object) [];
            $address_obj->lat = $address->lat;
            $address_obj->lng = $address->lng;
            $positions[$id] = $address_obj;
        }

        return response()->json([
            'status' => true,
            'data' => [
                'positions' => $positions,
                'area' => $restorant->radius ? $restorant->radius : null,
            ],
            'msg' => '',
        ]);
    }
}
