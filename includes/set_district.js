/*
( function ( $ ) {
	'use strict';
	$( document ).ready( function () {
		console.log( 'working!' )
	})
} ( jQuery ) )
*/

function setSelectOptions($select, data) {
	var regLabel = /\d-\W/g, $div, level;

	// reset html
	if ( $select.prop("tagName") == 'DIV' ) {
		level = 0;
		$div = $select,
		$div.html("");
	} else {
		level = 1 + $select.prevAll().length;
		$div = $select.parent()
		$select.nextAll().remove();
	}

	// make options html
	var html_options = seledted = i = label = val = '';
	for(i = 0; i < data.length; i++) {
		val = label = data[i].name;
		if(label.match(regLabel)) {
			val = "제" + label.split("-")[0] + "선거구";
			label = val+' : '+data[i].value;
		}
		selected = (i==0) ? 'selected="selected"' : '';
		html_options += '<option value="'+val+'" '+selected+' >'+label+'</option>';
	}

	// append select html
	if ( html_options ) {
		$div.append('<select level="'+level+'" data-index="0" onChange="onChangeDistrict(this);">'+html_options+'</select>');
		if ( Array.isArray(data[0].children) ) {
			$div.find('select[level="'+level+'"]').trigger('change');
		}
	}
}

function onChangeDistrict(el) {
	var $select = jQuery(el);
	var $prev = $select.prevAll();
	var level = parseInt($select.attr("level"));
	var value = $select.val();
	var data = district;
	var lv = i = 0;

	// find data-set
	if ( level == 0 ) {	// root
		// var data = district;
	} else if ( level == 1 ) {	// single
		i =  parseInt($prev.attr('data-index'));
		data = data[i].children;
	} else if ( level > 1 && $prev.length > 1 ) {	// multiple
		for ( lv=0; lv<level; ++lv ) {
			i =  parseInt($prev[lv].dataset.index);
			data = data[i].children;
		}
	}

	// assign data-index by selected value.
	for( i=0; i<data.length; i++) {
		if ( data[i].name === value ) {
			$select.attr('data-index',i);
			break;
		}
	}

	if ( data[i] && Array.isArray(data[i].children) ) {
		setSelectOptions($select, data[i].children);
	}
}

jQuery(document).ready(function($){
  //"use strict";
	
	$("#openDistrict").click( function(e){
		$("#layerDistrict").show();
		setSelectOptions( $("#divDistrict"), district );
	});

	$("#submitDistrict").click( function(e){
		var str = "";
		$("#divDistrict").find("select option:selected").each(function() {
			str += ">>" + $(this).val();
		});
		$("#textDistrict").val( str.substring(2) );
		$("#layerDistrict").hide();
	});

	$("#closeDistrict").click( function(e){
		$("#layerDistrict").hide();
	});

});

