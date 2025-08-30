<section class="w-full py-12 bg-white lg:py-12 hidden">
    <div class="max-w-6xl px-12 mx-auto text-center">
        <pre>Hidden form</pre>
        <form id="form-request-driver" method="POST" action="{{ route('order.store')}}">
            @csrf 
            @include('partials.input',['id'=>'issd','name'=>'Is Social Drive','placeholder'=>'Social drive','value'=>'1', 'required'=>true])
            @include('partials.input',['id'=>'driver_id','name'=>'Driver id','placeholder'=>'Driver id','value'=>'', 'required'=>true])
            @include('partials.input',['id'=>'phoneclient','name'=>'Phone','placeholder'=>'Phone','value'=>$_GET['phone'], 'required'=>true])
            @include('partials.input',['id'=>'comment','name'=>'Comment','placeholder'=>'Comment','value'=>'', 'required'=>true])
           
            @include('partials.input',['id'=>'pickup_address','name'=>'Pickup address','placeholder'=>'','value'=>$start['name'], 'required'=>true])
            @include('partials.input',['id'=>'delivery_address','name'=>'Delivery address','placeholder'=>'','value'=>$end['name'], 'required'=>true])
            
            @include('partials.input',['id'=>'delivery_lat','name'=>'delivery_lat','placeholder'=>'','value'=>$end['lat'], 'required'=>true])
            @include('partials.input',['id'=>'delivery_lng','name'=>'delivery_lng','placeholder'=>'','value'=>$end['lng'], 'required'=>true])
            
            @include('partials.input',['id'=>'pickup_lat','name'=>'pickup_lat','placeholder'=>'','value'=>$start['lat'], 'required'=>true])
            @include('partials.input',['id'=>'pickup_lng','name'=>'pickup_lng','placeholder'=>'','value'=>$start['lng'], 'required'=>true])
            
            <div class="text-center">
                <button type="submit" id="submitOrder" class="btn btn-primary my-4">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</section>

<!-- Section 3 -->
<script>
    var drivers=@json($drivers);
</script>
<section class="w-full py-12 bg-white lg:py-12">
    <div class="max-w-6xl px-12 mx-auto text-center">
        <div class="space-y-0 md:text-center">
            <div class="max-w-3xl mb-20 space-y-5 sm:mx-auto sm:space-y-4">
               
                <h3 class="text-4xl font-extrabold tracking-tight sm:text-5xl">{{count($drivers)}} {{ count($drivers)==1?__('taxi.driver_found_nearby'):__('taxi.drivers_found_nearby') }}</h3>
                @if (isset($distanceAndTime['distance']))
                    <p class="text-xl text-gray-500">{{ $distanceAndTime['distance']['text']}} - ±{{ $distanceAndTime['duration_in_traffic']['text']}} </p>
                @endif

                @if (isset($drivercategories)&&count($drivercategories)>0)
                    <div x-data="{ isOpen: false }" @mouseenter="isOpen = true" @mouseleave="isOpen = false" class="relative items-center  md:border-0 md:w-auto md:h-full" contenteditable="false">
                        <div class="cursor-pointer md:w-auto md:pl-0 focus:outline-none">
                            <span class="text-xl text-gray-800" id="selectedCategory">{{ __('All categories' )}}</span>
                        </div>

                        <div x-show="isOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1" class="absolute top-0 left-0 z-20 w-full mt-12 -ml-0 overflow-hidden transform bg-black border-2 border-gray-800 rounded-lg shadow-lg md:mt-10 lg:left-1/2 lg:-ml-24 md:w-48">

                            <div onclick="showDriverCategory('{{ __('All categories' )}}',0)" class="block p-4 px-5 text-sm text-gray-300 capitalize cursor-pointer hover:bg-gray-900 hover:text-gray-200">
                                {{ __('All categories' )}}
                            </div>
                            @foreach ($drivercategories as $drivercategory)
                                <div onclick="showDriverCategory('{{$drivercategory->title}}')" class="block p-4 px-5 text-sm text-gray-300 capitalize cursor-pointer hover:bg-gray-900 hover:text-gray-200">
                                {{ $drivercategory->title, $drivercategory->id}}
                                </div>
                            @endforeach
                        </div>
                    </div> 
                    <script type="text/javascript">
                    function showDriverCategory(dc,dcid){
                        $('#selectedCategory').html(dc);
                        $('.singleDriver').hide();
                        $('.'+dc).show();
                        if(dcid==0){
                            $('.singleDriver').show();
                        }
                    }
                    </script>
                @endif

                



               
            </div>
        </div>

        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-3">
           
            @foreach ($drivers as $index=>$driver)
            <div class="w-full border border-gray-200 rounded-lg shadow-sm singleDriver  @foreach ( $driver->drivercategories as  $key => $dc) {{ $dc->title }} @endforeach ">

                <div class="flex flex-col items-center justify-center p-10">
                    <img class="w-32 h-32 mb-6 rounded-full" src="{{ $driver->getConfig('avatar','/taxi/driver.png') }}">
                    <h2 class="text-lg font-medium">{{ $driver->name }}</h2>
                    <p class="font-medium text-green-500">{{ $driver->restaurant?$driver->restaurant->name:"" }}</p>
                    <p class="text-gray-400">{{ $driver->getConfig('vehicle','') }} {{ strlen($driver->getConfig('vehicle_color',''))>0?"-":""}} {{ $driver->getConfig('vehicle_color','') }}</p>
                    @if (strlen($driver->getConfig('plate_number',''))>0)
                        <p class="text-gray-400">{{ $driver->getConfig('plate_number','') }}</p>
                    @else
                        <br />
                    @endif
                    <p class="text-gray-400">
                        @foreach ( $driver->drivercategories as  $key => $dc)
                            {{ $dc->title }} 
                        @endforeach
                    </p>    
                </div>

                <div class="flex border-t border-gray-200 divide-x divide-gray-200">
                    <a  class="flex-1 block p-5 text-center text-gray-500 transition duration-200 ease-out hover:bg-gray-100 hover:text-gray-500">
                        {{ $driver->duration_text }}
                    </a>
                    <a  class="flex-1 block p-5 text-center text-gray-500 transition duration-200 ease-out hover:bg-gray-100 hover:text-gray-500">
                        ~{{ $driver->ride_cost_formated }}
                    </a>
                    <a  onclick="selectDriver('{{$index}}')" alt="whatsapp" class="modal-open flex-1 block p-5 text-center text-gray-300 transition duration-200 ease-out hover:bg-gray-100 hover:text-gray-500">
                       <img class="pl-5" style="height: 30px" src="/images/icons/common/whatsapp.svg" />
                    </a>
                </div>
            </div>
            @endforeach
            

           


        </div>

    </div>
</section>
