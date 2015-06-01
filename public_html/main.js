var testing = false ;
var verbose = true ;
var max_concurrent_uploads = 10 ;
var concurrent_uploads = 0 ;
var max_files_for_thumbnails = 600 ;
var reserved = {} ;
var tag2photo = {} ;
var exturlusage_count ;
var photos = [] ;

function runTool () {
	var uid = $('#user_id').val() ;
	var pid = $('#photoset_id').val() ;
	var phid = $('#photo_id').val() ;
	if ( uid != '' ) {
		if ( uid.match ( /^\d+@.+$/ ) ) getUserImagesByNSID ( uid ) ;
		else getUserImagesByName ( uid ) ;
	} else if ( pid != '' ) {
		getImagesByPhotoset ( pid ) ;
	} else if ( phid != '' ) {
		getSinglePhoto ( phid ) ;
	} else {
		alert ( "You gotta give me SOMETHING to work with here, man!" ) ;
	}
}

var flickr_api_url = 'https://secure.flickr.com/services/rest' ;

function getUserImagesByName ( name ) {
	$.getJSON ( flickr_api_url+'/?jsoncallback=?' , {
		method : 'flickr.people.findByUsername' ,
		api_key : flickr_api_key ,
		username : name ,
		format : 'json'
	} , function ( d ) {
		if ( undefined !== d.user && undefined !== d.user.nsid ) {
			$('#user_id').val ( d.user.nsid ) ;
			getUserImagesByNSID ( d.user.nsid ) ;
		} else {
			alert ( "Could not identify Flickr user " + name + ". Please use the user NSID." ) ;
		}
	} ) ;
}

function preRunClear () {
	$('#loaded').html ( '' ) ;
	$('#loading').show() ;
	$('#results').html ( '' ) ;
	photos = [] ;
}

function getUserImagesByNSID ( nsid ) {
	preRunClear () ;
	var params = {
		method : 'flickr.photos.search' ,
		result_key : 'photos' ,
		user_id : nsid
	} ;
	getFlickrImages ( params ) ;
}

function getImagesByPhotoset ( pid ) {
	preRunClear () ;
	var params = {
		method : 'flickr.photosets.getPhotos' ,
		result_key : 'photoset' ,
		privacy_filter : 1 ,
		photoset_id : pid
	} ;
	getFlickrImages ( params ) ;
}



function getSinglePhoto ( pid ) {
	preRunClear () ;
	var params = {
		method : 'flickr.photos.getInfo' ,
		photo_id : pid ,
		api_key : flickr_api_key ,
		format : 'json'
	} ;
	var photo = {} ;
	$.getJSON ( flickr_api_url+'/?jsoncallback=?' , params , function ( d ) {
		
		if ( d.stat != 'ok' ) {
			alert ( d.message ) ;
			return ;
		}
		
		$.each ( ['id','license','description'] , function ( k , v ) {
			photo[v] = d.photo[v] ;
		} ) ;
		photo.title = d.photo.title['_content'] ;
		photo.owner = d.photo.owner.nsid ;
		photo.datetaken = d.photo.dates.taken ;
		photo.pathalias = d.photo.owner.username ;
		
		var tags = [] ;
		$.each ( (d.photo.tags.tag||[]) , function ( k , v ) {
			if ( v.machine_tag == 0 ) tags.push ( v['_content'] ) ;
		} ) ;
		photo.tags = tags.join ( ' ' ) ;
		
		// Now load URLs
		params.method = 'flickr.photos.getSizes' ;
		$.getJSON ( flickr_api_url+'/?jsoncallback=?' , params , function ( d ) {
			if ( d.stat != 'ok' ) {
				alert ( d.message ) ;
				return ;
			}
			
			photo.width_o = 0 ;
			$.each ( d.sizes.size , function ( k , v ) {
				if ( v.label == 'Square' ) photo.url_q = v.source ;
				if ( v.label == 'Small' ) photo.url_s = v.source ;
				if ( v.width*1 > photo.width_o*1 ) {
					photo.width_o = v.width ;
					photo.height_o = v.height ;
					photo.url_best = v.source ;
				}
			} ) ;
		
			photos.push ( photo ) ;
			showResults() ;
		} ) ;
		
	} ) ;
}

function getSignature ( params ) {
	var keys = [] ;
	$.each ( params , function ( k , v ) { keys.push ( k ) } ) ;
	keys.sort() ;
	var s = '' ;
	$.each ( keys , function ( dummy , key ) { s += key + params[key] } ) ;
	return md5(escape(s)) ;
}

function getFlickrImages ( params , page ) {
	var max_pics = ($('#max_pics').val()||999999999) ;
	if ( page === undefined ) page = 1 ;
	
	var tags = $('#flickr_tags').val() ;
	if ( tags != '' ) params.tags = tags ;
	
//	params.safe_search = 2 ;
	params.api_key = flickr_api_key ;
	params.extras = 'description,license,date_taken,geo,tags,url_o,url_l,url_m,url_q,url_s,path_alias' ;
	params.per_page = max_pics<500?max_pics:500 ;
	params.page = page ;
	params.format = 'json' ;
	
//	params.api_sig = getSignature ( params ) ; // Does not work

	
	$.getJSON ( flickr_api_url+'/?jsoncallback=?' , params , function ( d ) {
		if ( d.stat == 'fail' ) {
			$('#loading').hide() ;
			alert ( d.message ) ;
			return ;
		}
		$.each ( d[params.result_key].photo , function ( k , v ) {
			if ( undefined === flicker_license[v.license] ) return ; // No free license
			
			// Here, we compensate for idiotic Flickr restrictions on free accounts
			if ( undefined !== v.url_o ) v.url_best = v.url_o ;
			else if ( undefined !== v.url_l ) v.url_best = v.url_l ;
			else if ( undefined !== v.url_m ) v.url_best = v.url_m ;
			if ( undefined === v.url_best ) return ; // No usable resolution found
			
			photos.push ( v ) ;
			if ( photos.length >= max_pics ) return false ;
		} ) ;
		$('#loaded').html ( photos.length + " of ca. " + d[params.result_key].total + " pictures checked for compatible licenses" ) ;
		if ( d[params.result_key].pages > d[params.result_key].page && photos.length < max_pics ) getFlickrImages ( params , page+1 ) ; // Get 'em all
		else showResults() ;
	} ) ;
}

function showResults() {

	if ( photos.length == 0 ) {
		$('#loading').hide() ;
		$('#results').html ( "No suitable photos found. Maybe they are not under a free license?" ) .show() ;
		return ;
	}

	var h = '' ;
	
	h += "<div id='transfer_params'>" 
	h += "<table class='table table-condensed'>" ;
//	h += "<tr><th class='span3'>Before/after title</th><td class='span6'><input title=\"Don't forget the spaces!\" type='text' id='title_before'/>_TITLE_<input type='text' id='title_after' title=\"Don't forget the spaces!\"/></td></tr>" ;
	h += "<tr><th>Add to every description</th><td><textarea class='span6' rows=3 id='desc_add'></textarea>" ;
	h += "<br/><label class='checkbox'><input type='checkbox' id='no_auto_desc' /> Do not use automatic description from Flickr</label>" ;
	h += "</td></tr>" ;
	h += "<tr><th>Append everywhere (categories etc.)<br/><a href='#' id='add_cat'>Add category</a></th><td><textarea class='span6' rows=3 id='info_add'></textarea></td></tr>" ;
	h += "<tr><td/><td><button onclick='initiateUpload();return false' class='btn-primary'><i class='icon-arrow-right icon-white'></i> Transfer selected files to Commons</button></td></tr>" ;
	h += "</table>" ;
	h += "</div>" ;

	$('.initial_hidden').show() ;
	
	tag2photo = {} ;
	
	if ( photos.length > max_files_for_thumbnails ) h += "<div><i>More than " + max_files_for_thumbnails + " files, not displaying thumbnails. Your browser will thank you.</i></div>" ;
	h += "<div id='commons_lookup' >Checking Commons for existing files... <span class='count'></span></div>" ;
	h += "<table id='results_table' cellspacing=0 cellpadding=0>" ;
	$.each ( photos , function ( k , p ) {
		p.flickr_page = 'https://www.flickr.com/photos/' + p.owner + '/' + p.id ;
		if ( p.owner === undefined ) p.flickr_page = "https://secure.flickr.com/photo.gne?id=" + p.id ;
		
		$.each ( p.tags.split(' ') , function ( k2 , tag ) {
			if ( tag.match ( /^\d+$/ ) ) return ; // No "pure number" tags
			if ( tag.match ( /^\s*$/ ) ) return ; // No empty tags
			if ( undefined === tag2photo[tag] ) tag2photo[tag] = [] ;
			tag2photo[tag].push ( k ) ;
		} ) ;
		
		h += "<tr id='photo_row_" + k + "' key='" + k + "' class='prow'>" ;
		h += "<td><input class='useit' type='checkbox' checked /></td>" ;
		h += "<td nowrap><a href='" + p.flickr_page + "' target='_blank'><img border=0 class='pthumb' /></a></td>" ;
		h += "<td class='descbox'>" ;
		h += "<a target='_blank' href='" + p.flickr_page + "'><b class='ptitle'></b></a> " ;
		h += "<span style='color:#999999'>(" + p.id + ")</span>" ;
		h += "<br/>" ;
		h += "<input class='newtitle span6' type='text' /><br/>" ;
		h += p.datetaken + " / " ;
		if ( undefined !== p.width_o ) h += p.width_o + "&times;" + p.height_o + "px / " ;
		h += flicker_license[p.license] ;
		h += "<div><i>" + p.tags + "</i></div>" ;
		h += "<div><table border=0>" ;
		h += "<tr><td>Description</td><td><textarea title='Overrides automatic description' class='manual_desc span6' rows=2></textarea></td></tr>" ;
		h += "<tr><td>Categories</td><td><textarea title='No category prefix; one category per row; overrides automatic category detection' class='manual_cats span6' rows=2></textarea></td></tr>" ;
		h += "<tr><td/><td><label><input type='checkbox' class='auto_cats' checked /> Automatically detect categories if none are given manually</label></td></tr>" ;
		h += "</table></div>" ;
		h += "<span class='pdesc'></span>" ;
		h += "<div class='transfer_result'></div>" ;
		h += "</td>" ;
		h += "</tr>" ;
	} ) ;
	h += "</table>" ;

	$('#loading').hide() ;
	$('#results').html ( h ) ;
	
	$('#add_cat').click ( function () {
		var p = prompt ( 'Enter a category to add:' ) ;
		if ( p == null ) return ;
		var info_add = $('#info_add').val() ;
		var s = $.trim(info_add) ;
		if ( s != '' ) s += "\n" ;
		s += "[[Category:" + p + "]]" ;
		$('#info_add').val(s);
		return false ;
	} ) ;
	
	$('#title_before').tooltip() ;
	$('#title_after').tooltip() ;

	var user_urls = {} ;
	var url2key = {} ;
	$.each ( photos , function ( k , p ) {
		var id = '#photo_row_' + k ;
		var o = $(id) ;
		var ntr = p.title.replace(/\.jpe{0,1}g$/,'').replace(/\[/g,'(').replace(/\]/g,')').replace(/[\|\/\#\:]/g,'-') ;
		o.find('input.newtitle').val ( ntr + " (" + p.id + ").jpg" ) ;
		o.find('input.newtitle').keyup ( function () { updateFileExistStatus ( $($(this).parents('td')).get(0) ) } ) ;
		o.find('.ptitle').html ( p.title ) ;
		o.find('.pdesc').html ( p.description['_content'] ) ;
		if ( photos.length <= max_files_for_thumbnails ) o.find('img.pthumb').attr ( 'src' , p.url_s ) ; // url_q
		else o.find('img.pthumb').remove() ;
		user_urls['www.flickr.com/photos/'+p.owner] = 1 ;
		url2key['www.flickr.com/photos/'+p.owner+'/'+p.id] = k ;
		if ( p.pathalias !== undefined ) {
			user_urls['www.flickr.com/photos/'+p.pathalias] = 1 ;
			url2key['www.flickr.com/photos/'+p.pathalias+'/'+p.id] = k ;
		}
	} ) ;

	exturlusage_count = 0 ;
	$.each ( user_urls , function ( base_url , v ) { exturlusage_count++; } ) ;
	$.each ( user_urls , function ( base_url , v ) {
		checkCommonsURLs ( base_url , url2key ) ;
	} ) ;

	showTagFilter() ;
	
	$("td.descbox").each ( function ( k , v ) { updateFileExistStatus ( v ) ; } ) ;


	$('#results').show() ;
}

function updateFileExistStatus ( v ) {
	var input = $($($(v).find('input.newtitle')).get(0)) ;
	var tr = $($(input.parents('tr')).get(0)) ;
	if ( tr.hasClass('upload_running') || tr.hasClass('upload_ok') || tr.hasClass('upload_failed') ) {
		$($(v).find('div.file_exists_warning')).remove() ;
		return ;
	}
	var title = input.val() ;
	var wp = new WikiPage ( { lang:'commons' , project:'wikimedia' , title:title , ns:6 } ) ;
	wp.checkExists ( function () { // Yes
		if ( $($(v).find('div.file_exists_warning')).length > 0 ) return ;
		$(v).prepend ( "<div class='file_exists_warning' style='float:right' title='A file with that name exists on Commons!'><img src='//upload.wikimedia.org/wikipedia/commons/8/88/Red_triangle_alert_icon.png' width='20px' /></div>" ) ;
	} , function () { // No
		$($(v).find('div.file_exists_warning')).remove() ;
	} ) ;
}

function prefixSelectedFileNames() {
	var pre = prompt ( "String to put in front of all selected filenames (space will be added)" ) ;
	if ( pre == null || pre == '' ) return ;
	$("input.useit:checked").each ( function () {
		var tr = $($(this).parents('tr').get(0)) ;
		var i = $('input.newtitle',tr) ;
		i.val ( pre + ' ' + i.val() ) ;
	} ) ;
}

function showTagFilter() {
	var h = "<div class='well well-small' id='tag_filter_container'><form class='form-inline'><div>" ;
	h += "<label><input type='radio' name='tag_mark' value='1' checked /> Select</label>/" ;
	h += "<label><input type='radio' name='tag_mark' value='0' /> de-select</label>" ;
	h += " all photos " ;
	h += "<label><input type='radio' name='tag_with' value='1' checked /> with</label>/" ;
	h += "<label><input type='radio' name='tag_with' value='0' /> without</label>" ;
	h += " tag " ;
	h += "<select id='tag'></select> " ;
	h += "<button class='btn-info'>Change selections</button>" ;
	h += "</div><div>" ;
	h += "<button class='btn-info' onclick='$(\"#results input.useit[type=checkbox]\").attr(\"checked\",true); return false'>Select all</button> " ;
	h += "<button class='btn-info' onclick='$(\"#results input.useit[type=checkbox]\").removeAttr(\"checked\"); return false'>Deselect all</button> " ;
	h += "<button class='btn-info' onclick='prefixSelectedFileNames(); return false'>Prefix selected names</button>" ;
	h += " <span id='tag_filter_message'></span></div>" ;
	h += "<div style='margin-top:2px'>Auto-detect categories: " ;
	h += "<button class='btn-info' onclick='$(\"input.auto_cats\").attr(\"checked\",true);return false'>Yes</button> " ;
	h += "<button class='btn-info' onclick='$(\"input.auto_cats\").removeAttr(\"checked\");return false'>No</button> " ;
	h += "(default is 'yes', can also be changed individually)</div>" ;
	h += "</form></div>" ;
	$('#transfer_params').after ( h ) ;
	$('#tag_filter_container form').submit ( function () { tag_filter() ; return false } ) ;

	var tk = [] ;
	$.each ( tag2photo , function ( tag , ph ) { tk.push ( tag ) ; } ) ;
	tk.sort() ;
	
	$.each ( tk , function ( dummy , tag ) {
		var o = $('<option></option') ;
		o.html ( tag + " (" + tag2photo[tag].length + " photos)" ) ;
		o.attr ( 'value' , tag ) ;
		$('#tag').append ( o ) ;
	} ) ;
}

function tag_filter () {
	var tag_mark = $("input[name='tag_mark']:checked").val() ;
	var tag_with = $("input[name='tag_with']:checked").val() ;
	var tag = $('#tag option:selected').attr('value') ;
	var changed = 0 ;
	
	if ( tag_with == 1 ) {
		$.each ( tag2photo[tag] , function ( dummy , key ) {
			var state = $('#photo_row_'+key+' input.useit').attr('checked') ;
			if ( (state && tag_mark==0) || (!state && tag_mark==1) ) changed++ ;
			if ( tag_mark == 1 ) $('#photo_row_'+key+' input.useit').attr('checked',true) ;
			else $('#photo_row_'+key+' input.useit').removeAttr('checked') ;
		} ) ;
	} else {
		$.each ( tag2photo , function ( tag1 , dummy1 ) {
			if ( tag == tag1 ) return ;
			$.each ( tag2photo[tag1] , function ( dummy , key ) {
				var state = $('#photo_row_'+key+' input.useit').attr('checked') ;
				if ( (state && tag_mark==0) || (!state && tag_mark==1) ) changed++ ;
				if ( tag_mark == 1 ) $('#photo_row_'+key+' input.useit').attr('checked',true) ;
				else $('#photo_row_'+key+' input.useit').removeAttr('checked') ;
			} ) ;
		} ) ;
	}
	
	$('#tag_filter_message').html ( changed + " photos " + ( tag_mark == 1 ? 'checked' : 'unchecked' ) ) ;
}

function checkCommonsURLs ( base_url , url2key ) {
	var wp = new WikiPage ( { lang:'commons' , project:'wikimedia' , title:'Test' } ) ;
	wp.getExtLinkUsage ( {
		ns : 6 ,
		query : base_url ,
		callback : function ( d ) {
			$.each ( d.exturlusage , function ( k , v ) {
				var url = v.url.replace(/\/+$/,'').replace(/^[a-z]+:\/\//,'') ;
				if ( undefined === url2key[url] ) return ;
				var id = '#photo_row_' + url2key[url] ;
				var o = $(id) ;
				o.find('.useit').remove() ;
				var h = $("<div style='color:red'>Already exists on Commons as <a target='_blank' href=''></a></div>") ;
				var a = $(h.find('a')) ;
				a.attr('href','//commons.wikimedia.org/wiki/'+encodeURIComponent(v.title)) ;
				a.html(v.title.replace(/^[^:]:/,'')) ;
				o.find('.pdesc').append ( h ) ;
				o.addClass ( 'oncommons' ) ;
			} ) ;

			exturlusage_count-- ;
			$('#commons_lookup span.count').html ( "(" + exturlusage_count + " left)" ) ;
			if ( exturlusage_count == 0 ) {
				var h = "<label style='display:inline'><input id='show_only_not_on_commons' type='checkbox' style='display:inline'/> Show only files not linked from Commons (" ;
				h += ( $('.prow').length - $('.oncommons').length ) + " out of " + $('.prow').length ;
				h += ")</label><hr/>" ;
				$('#commons_lookup').html ( h ) ;
				$('#show_only_not_on_commons').change ( function () {
					$('.oncommons').toggle() ;
				} ) ;
				$('#show_only_not_on_commons').prop("checked", true);
				$('.oncommons').toggle() ;
			}

		}
	} ) ;
}

var max_tranfer ;
var done_transfer ;

function initiateUpload () {
	var h = '<div class="progress progress-striped active">' ;
	h += '<div class="bar" id="progress_bar" style="width: 0%;"></div>' ;
	h += '</div>' ;
	
	done_transfer = 0 ;
	max_transfer = $('#results_table input.useit[type="checkbox"]:checked').length ;
	
	$('#transfer_params').after ( h ) ;
	doUpload () ;
}

function updateProgressBar () {
	var p = Math.floor ( done_transfer * 100 / max_transfer ) ;
	$('#progress_bar').width ( p + '%' ) ;
	if ( done_transfer == max_transfer ) {
		$('div.progress').remove() ;
	}
}

// Uploads a single file
function doUpload () {
	$($('#results_table input.useit[type="checkbox"]:checked').get(0)).parents('tr').each ( function () {
		var o = $(this) ;
		o.find('input.useit[type="checkbox"]').remove() ; // Uploading
		var key = o.attr('key') ;
   		$('#photo_row_'+key).addClass('upload_running') ;
		var flickr_url = photos[key].flickr_page ;
		getFreeCommonsTitle ( key , 1 ) ;
		return false ;
	} ) ;
}

function getFreeCommonsTitle ( key , num ) {
//	var title = photos[key].title.replace ( /\.jpe{0,1}g$/i , '' ) ;
//	title = $('#title_before').val() + title + $('#title_after').val() ;
//	title += " (" + photos[key].id + ")" ;
	var title = $('#photo_row_'+key+' input.newtitle').val() ;
	if ( num > 1 ) {
		title = title.replace(/\.jpe{0,1}g$/i,'') + ' (' + num + ').jpg' ;
	}
//	if ( num > 1 ) title += " (" + num + ")" ;
//	title += ".jpg" ;
	
	if ( undefined !== reserved[title] ) {
		getFreeCommonsTitle ( key , num+1 ) ;
		return ;
	}
	var wp = new WikiPage ( { lang:'commons' , project:'wikimedia' , title:title , ns:6 } ) ;
	wp.checkExists ( function () { // Yes
		getFreeCommonsTitle ( key , num+1 ) ;
	} , function () { // No
		if ( undefined !== reserved[title] ) {
			getFreeCommonsTitle ( key , num+1 ) ; // Paranoia
			return ;
		}
		reserved[title] = 1 ;
		transferFile ( key , title ) ;
		
		concurrent_uploads++ ;
		if ( concurrent_uploads >= max_concurrent_uploads ) return ;
		doUpload() ; // Load next file
	} ) ;
}

function uploadToCommons ( key , title , desc ) {
//	var desc_add = $('#desc_add').val() ;
	var info_add = $('#info_add').val() ;

//	if ( desc_add != '' ) desc = desc.replace ( /\|\s*Source=/ , "<br/>" + desc_add + "\n| Source=" ) ;
	if ( info_add != '' ) desc = desc + "\n" + info_add ;
	
	if ( verbose ) console.log ( "Uploading... " + title ) ;

	var params = {
		action:'upload',
		newfile:title,
		url:photos[key].url_best,
		desc:desc,
		comment:'Transferred from Flickr via [[Commons:Flickr2Commons|Flickr2Commons]]',
		botmode:1
	} ;
//	console.log ( params ) ;
	
	
	$.post ( '/magnustools/oauth_uploader.php?rand='+Math.random() , params , function ( d ) {
		var tr = "<tt>" + title + "</tt>" ;
		if ( d.error == 'OK' ) {
			tr = "Now at : <a target='_blank' href='//commons.wikimedia.org/wiki/File:" + escape ( title ) + "'>" + tr + "</a>" ;
			$('#photo_row_'+key).removeClass('upload_running').addClass('upload_ok') ;
		} else {
			$('#photo_row_'+key).removeClass('upload_running').addClass('upload_failed') ;
			var s = [ d.error ] ;
			$.each ( (((d.res||{}).upload||{}).warnings||{}) , function ( k3 , v3 ) {
				if ( typeof v2 == 'array' ) {
					s.push ( k3 + ': ' + v3.join('; ') ) ;
				} else {
					s.push ( k3 + ': ' + v3 ) ;
				}
			} ) ;
			tr += "<br/><b>Transfer failed [1] : " + s.join('/') + "</b>" ;
//			console.log ( d ) ;
		}
		if ( verbose ) console.log("complete : " + title ); 
		if ( verbose ) console.log ( d ) ;
		$('#photo_row_'+key+' div.transfer_result').show().html(tr);
		concurrent_uploads-- ;
		done_transfer++ ;
		updateProgressBar() ;
		doUpload() ; // Next
	} , 'json' )

	.error(function(x) {
		$('#photo_row_'+key).removeClass('upload_running').addClass('upload_failed') ;
		var tr = "<tt>" + title + "</tt>" ;
		tr += "<br/><b>Transfer failed [2] : " + x.status + " " + x.statusText + "</b>" ;
		$('#photo_row_'+key+' div.transfer_result').show().html(tr);
		concurrent_uploads-- ;
		done_transfer++ ;
		updateProgressBar() ;
		doUpload() ; // Next
	});		
	
}

var max_concurrent = 2 ;
var running = 0 ;

function transferFile ( key , title ) {
	if ( running >= max_concurrent ) {
		setTimeout ( function () {
			transferFile ( key , title ) ;
		} , 1000 ) ;
		return ;
	}
	running++ ;

	$('#photo_row_'+key+' input.newtitle').val(title) ;
	
	var debug_title = photos[key].id + " / " + title ;
	if ( verbose ) console.log ( 'Requesting description... ' + debug_title ) ;

	var params = {
		id : photos[key].id ,
//		admin : 'File Upload Bot (Magnus Manske)' ,
//		uploading_user : tusc.user ,
		raw : 'on' ,
		format : 'json'
	} ;
	
	var manual_desc = $('#photo_row_'+key+' textarea.manual_desc').val() ;
	if ( manual_desc != '' ) params.description = manual_desc ;

	var auto_cats = $('#photo_row_'+key+' input.auto_cats').is(':checked') ;
	var manual_cats = $('#photo_row_'+key+' textarea.manual_cats').val() ;
	if ( manual_cats != '' ) params.categories = manual_cats.split("\n").join(';') ;
	else if ( !auto_cats ) params.categories = ' ' ; // No auto categories
	

//	$.getJSON ( "http://wikipedia.ramselehof.de/flinfo.php?callback=?" , params , function ( d ) {
	$.get ( 'flinfo_proxy.php' , params , function ( d ) {
		
		if ( undefined === d.wiki || d.wiki.status != 0 ) {
			var err = "Flinfo issue " + d.wiki.status ;
//			console.log ( err ) ;
			$('#photo_row_'+key+' div.transfer_result').show().html(err);
			$('#photo_row_'+key).removeClass('upload_running').addClass('upload_failed') ;
			concurrent_uploads-- ;
			done_transfer++ ;
			running-- ;
			updateProgressBar() ;
			doUpload() ; // Next
			return ;
		}

		var final_desc = ( d.wiki.info.desc || '' ) ;
		if ( manual_desc == '' && $('#no_auto_desc').is(':checked') ) final_desc = '' ;
		var desc_add = $('#desc_add').val() ;
		if ( desc_add != '' ) {
			if ( final_desc != '' ) final_desc += "<br/>\n" ;
			final_desc += desc_add + "\n" ;
//			final_desc = desc.replace ( /\|\s*Source=/ , "<br/>" + desc_add + "\n| Source=" ) ;
		}
		
		var w = "== {{int:filedesc}} ==\n" ;
		w += "{{Information\n" ;
		w += "| Description = " + final_desc + "\n" ;
		w += "| Source      = " + ( d.wiki.info.source || '' ) + "\n" ;
		w += "| Date        = " + ( d.wiki.info.date || '' ) + "\n" ;
		w += "| Author      = " + ( d.wiki.info.author || '' ) + "\n" ;
		w += "| Permission  = " + ( d.wiki.info.permission || '' ) + "\n" ;
		w += "| other_versions=\n" ;
		w += "}}\n" ;
		
		if ( undefined !== d.wiki.geolocation && undefined !== d.wiki.geolocation.latitude ) {
			w += "{{Location dec|" + d.wiki.geolocation.latitude + "|" + d.wiki.geolocation.longitude + "|source:" + d.wiki.geolocation.source + "}}\n" ;
		}
		
		w += "\n=={{int:license-header}}==\n" ;
		$.each ( ( d.wiki.licenses || [] ) , function ( k , v ) {
			w += "{{" + v + "}}\n" ;
		} ) ;
		
		w += "\n" ;
		$.each ( ( d.wiki.categories || [] ) , function ( k , v ) {
			w += "[[" + v + "]]\n" ;
		} ) ;
		
		w = $.trim ( w ) ;
		
		if ( testing ) { console.log ( w ) ; running-- ; return ; }
		
		uploadToCommons ( key , title , w ) ;
		running-- ;
		
	} , 'json' ) ;

}

function showExample ( mode , data ) {
	if ( mode == 1 ) {
		$('#user_id').val ( data ) ;
		runTool() ;
	} else if ( mode == 2 ) {
		$('#photoset_id').val ( data ) ;
		runTool() ;
	} else if ( mode == 3 ) {
		$('#photo_id').val ( data ) ;
		runTool() ;
	}
}

$(document).ready ( function () {
/*	if ( window.location.protocol == 'https:' ) { // Force-redirect to http, to use flinfo
		window.location = window.location.href.replace(/^https:/,'http:') ;
	}*/
	loadMenuBarAndContent ( { toolname : 'Flickr2commons' , meta : 'Flickr2commons' , content : 'form.html' , run : function () {
			wikiDataCache.ensureSiteInfo ( [ { lang:'commons' , project:'wikimedia' } ] , function () {
	
			$('#toolname').html ( "Flickr-to-Commons" ) ;
//			tusc.initializeTUSC () ;
//			tusc.addTUSC2toolbar() ;

			$('#user_id').tooltip ( { placement : 'right' , trigger: 'hover' } ) ;
			$('#photoset_id').tooltip ( { placement : 'right' , trigger: 'hover' } ) ;
			$('#photo_id').tooltip ( { placement : 'right' , trigger: 'hover' } ) ;
			$('#flickr_tags').tooltip ( { placement : 'right' , trigger: 'hover' } ) ;
			$('#max_pics').tooltip ( { placement : 'right' , trigger: 'hover' } ) ;
			$('.initial_hidden').hide() ;
			$('.initial_hidden input').tooltip ( { placement : 'right' , trigger: 'hover' } ) ;
			
			$('#user_id').focus() ;
			
			var params = getUrlVars() ;
			if ( undefined !== params.userid ) $('#user_id').val ( params.userid ) ;
			if ( undefined !== params.maxpics ) $('#max_pics').val ( params.maxpics ) ;
			if ( undefined !== params.tags ) $('#flickr_tags').val ( params.tags ) ;
			testing = ( undefined !== params.testing ) ;
			
			if ( testing ) return ;
			
			if ( undefined !== params.userid ) { showExample(1,params.userid) ; }
			else if ( undefined !== params.photoset ) { showExample(2,params.photoset) ; }
			else if ( undefined !== params.photoid ) { showExample(3,params.photoid) ; }
			
		} ) ;
	} } )
} ) ;

//		tusc.setupLoginBar ( $('#tusc_container_wrapper') , function () { } ) ;
