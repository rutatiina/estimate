<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('estimates')->group(function () {

        Route::get('query', 'Rutatiina\Estimate\Http\Controllers\EstimateController@query');
        //Route::get('summary', 'Rutatiina\Estimate\Http\Controllers\EstimateController@summary');
        Route::post('export-to-excel', 'Rutatiina\Estimate\Http\Controllers\EstimateController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\Estimate\Http\Controllers\EstimateController@approve');
        //Route::post('contact-estimates', 'Rutatiina\Estimate\Http\Controllers\Sales\ReceiptController@estimates');
        Route::get('{id}/copy', 'Rutatiina\Estimate\Http\Controllers\EstimateController@copy');

        Route::post('{txnId}/process', 'Rutatiina\Estimate\Http\Controllers\EstimateController@process');
        Route::get('{txnId}/process/{processTo}', 'Rutatiina\Estimate\Http\Controllers\EstimateController@process');

    });

    Route::get('estimates/vue/blade', 'Rutatiina\Estimate\Http\Controllers\EstimateController@vueAndBlade');

    Route::resource('estimates/settings', 'Rutatiina\Estimate\Http\Controllers\SettingsController');
    Route::resource('estimates', 'Rutatiina\Estimate\Http\Controllers\EstimateController');

});
