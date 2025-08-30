@if (count($faq)>0)
    <div id="faq" class="section">
        <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto text-center">
            <h3 class="display-3 ckedit" key="explain_maintitle" id="explain_maintitle">{{ __('FAQ') }}</h3>
            </div>
        </div>
        <div class="row align-items-center mt-5">
            <div class="col-12">
            @foreach ($faq as $key => $process)
                <div class="info info-horizontal info-hover-primary mt-5">
                <div class="description pl-4">
                    <h5 class="title">{{ $process->title }}</h5>
                    <p>{{ $process->description }}</p>
                </div>
                </div>
            @endforeach
            </div>
        </div>
        </div>
    </div>   
@endif
