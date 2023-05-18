@extends('templates.main')

@section('title_page')
    Payment Request
@endsection

@section('breadcrumb_title')
    approved
@endsection

@section('content')
    <div class="row">
      <div class="col-12">

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Edit Payment Request - Advance</h3>
            <a href="{{ route('mypayreqs.index') }}" class="btn btn-sm btn-primary float-right"><i class="fas fa-arrow-left"></i> Back</a>
          </div>
          <div class="card-body">
            <form action="{{ route('payreq-advance.update', $payreq->id) }}" method="POST">
              @csrf @method('PUT')

              <input type="hidden" name="form_type" value="advance">
              <div class="form-group">
                <label for="payreq_no">Payreq No</label>
                <input type="text" name="payreq_no" value="{{ $payreq->payreq_no }}" class="form-control" disabled>
              </div>

              <div class="form-group">
                <label for="remarks">Purpose</label>
                <textarea name="remarks" id="remarks" cols="30" rows="2" class="form-control @error('remarks') is-invalid @enderror" autofocus>{{ old('remarks', $payreq->remarks) }}</textarea>
                @error('remarks')
                <div class="invalid-feedback">
                  {{ $message }}
                </div>
                @enderror
              </div>

              <div class="form-group">
                <label for="amount">Amount</label>
                <input type="text" name="amount" id="amount" value="{{ old('amount', $payreq->amount) }}" class="form-control @error('amount') is-invalid @enderror">
                @error('amount')
                <div class="invalid-feedback">
                  {{ $message }}
                </div>
                @enderror
              </div>

              <div class="card-footer">
                <div class="row">
                  <div class="col-6">
                    <button type="submit" class="btn btn-primary btn-block" id="btn-draft"><i class="fas fa-save"></i> Save as Draft</button>
                  </div>
                  <div class="col-6">
                    <button type="submit" class="btn btn-warning btn-block" id="btn-submit"><i class="fas fa-paper-plane"></i> Save and Submit</button>
                  </div>
                </div>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
@endsection

@section('styles')
  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('adminlte/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endsection

@section('scripts')
<!-- Select2 -->
<script src="{{ asset('adminlte/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
  $(function () {
    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  }) 

  // btn-save as draft
  $('#btn-draft').click(function() {
    // add attribute name="draft" to form
    $('form').append('<input type="hidden" name="draft" value="1">');
  });

  // btn-save and submit
  $('#btn-submit').click(function() {
    // add attribute name="draft" to form
    $('form').append('<input type="hidden" name="draft" value="0">');
  });
</script>
@endsection