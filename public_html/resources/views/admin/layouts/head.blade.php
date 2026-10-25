<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#123c45">
  <title>@yield('title', 'Dashboard') · Clapslearn</title>
  <!-- Theme style -->
  <link rel="stylesheet" href="{{asset('css/app.css')}}">
  <!-- DataTables -->
  <link rel="stylesheet" href="{{asset('adminlte/dataTables.bootstrap4.min.css')}}">
  <link rel="stylesheet" href="{{asset('adminlte/responsive.bootstrap4.min.css')}}">
  <link rel="stylesheet" href="{{asset('adminlte/buttons.bootstrap4.min.css')}}">
  <link type="text/css" rel="stylesheet" href="{{asset('adminlte/toastr.css')}}">
  <link rel="stylesheet" href="{{asset('adminlte/select2.min.css')}}" type="text/css"/>
  <link rel="stylesheet" href="{{asset('css/custom.css')}}" type="text/css"/>
</head>
