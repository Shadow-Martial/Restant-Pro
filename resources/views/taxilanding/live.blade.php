<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('global.site_name','FindMeTaxi') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor') }}/tailwind/tailwind.min.css">


    <!-- Google Analitics -->
    @include('layouts.ga')
    @yield('head')
    @laravelPWA
    
    <!-- RTL and Commmon ( Phone ) -->
    @include('layouts.rtl')

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

    <!-- Custom CSS defined by admin -->
    <link type="text/css" href="{{ asset('byadmin') }}/front.css" rel="stylesheet">

    <style>
        .modal {
          transition: opacity 0.25s ease;
        }
        body.modal-active {
          overflow-x: hidden;
          overflow-y: visible !important;
        }

        #map_location {
            height: 100vh;
            /* The height is 400 pixels */
            width: 100%;
            /* The width is the width of the web page */
        }
      </style>

    <script src="{{ asset('argon') }}/vendor/jquery/dist/jquery.min.js"></script>
    
</head>
<body class="landing-page">
    <div id="map_location"></div>
<script>
    var ordermd = "{{ $order->md}}";
    var mapInitialized=false;
    function initializeMap(lat, lng){
        
        var map_options = {
            zoom: 15,
            center: new google.maps.LatLng(lat, lng),
            mapTypeId: "terrain",
            scaleControl: true
        }
        if(!mapInitialized){
            map_location = new google.maps.Map( document.getElementById("map_location"), map_options );
           
        }else{
            marker.setMap(null)
        }

        marker = new google.maps.Marker({
            position: new google.maps.LatLng(lat, lng),
            map: map_location,
            //icon: start
        });
        

        mapInitialized=true;

    }

    function refreshLocation(){
        getLocation(function(isFetched, currPost){
            if(isFetched){
                if(currPost.lat != 0 && currPost.lng != 0){
                    //initialize map
                    initializeMap(currPost.lat, currPost.lng)
                    
                }
            }
        });
    }

    window.onload = function () {
        refreshLocation();
        setInterval(refreshLocation, 4000);
    }

    function getLocation(callback){
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        console.log(ordermd);
        $.ajax({
            type:'GET',
            url: '/ll/'+ordermd,
            success:function(response){
                if(response.status){
                    return callback(true, response)
                }
        }, error: function (response) {
            return callback(false, response.responseJSON.errMsg);
            }
        })
    }

    </script>
    <!-- Google Map -->
    <script async defer src= "https://maps.googleapis.com/maps/api/js?key=<?php echo config('settings.google_maps_api_key'); ?>"> </script>
 
</body>
</html>