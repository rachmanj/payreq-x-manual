<form action="{{ route('cashier.migrasi.payreqs.destroy', $model->id) }}" method="POST">
  @csrf
  <input type="hidden" name="payreq_id" value="{{ $model->id }}">
  <input type="hidden" name="payreq_migrasi_id" value="{{ $model->PayreqMigrasi->id }}">
  <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Are You sure You want to pay this payreq?')">delete</button>
</form>  

