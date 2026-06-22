@extends('layouts.app')
@section('title', 'Penawaran Baru')

@section('content')
<div class="mb-4">
    <a href="{{ route('quotations.index') }}" class="text-sm text-slate-500 hover:text-slate-700"><i class="bi bi-arrow-left"></i> Kembali</a>
    <h2 class="mt-1 text-lg font-semibold text-slate-800">Buat Penawaran Baru</h2>
</div>

@include('quotations._form', ['action' => route('quotations.store'), 'method' => 'POST'])
@endsection
