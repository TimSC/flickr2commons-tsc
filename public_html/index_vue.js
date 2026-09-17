// INITIALIZE
var is_localhost = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(location.hostname) ;
if (location.protocol != 'https:' && !is_localhost) location.href = 'https:' + window.location.href.substring(window.location.protocol.length); // ENFORCE HTTPS
else if ( /^[^#]+\?/.test(window.location.href) ) {  // AUTO-FORWARD BASED ON OLD VERSION PARAMETERS

function getUrlVars () {
		var vars = {} ;
		var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).replace(/#.*$/,'').split('&');
		$.each ( hashes , function ( i , j ) {
			var hash = j.split('=');
			hash[1] += '' ;
			vars[hash[0]] = decodeURI(hash[1]).replace(/_/g,' ');
		} ) ;
		return vars;
	}
	var params = getUrlVars() ;

	var add = [] ;
	if ( typeof params.tags != 'undefined' ) add.push ( 'tag='+encodeURIComponent(params.tags) ) ;

	var url = '/' ;
	if ( typeof params.userid != 'undefined' ) url = '/user/' + encodeURIComponent ( params.userid ) ;
	else if ( typeof params.photoset != 'undefined' ) url = '/photoset/' + encodeURIComponent ( params.photoset ) ;
	else if ( typeof params.photoid != 'undefined' ) url = '/photo/' + encodeURIComponent ( params.photoid ) ;
	if ( add.length > 0 ) url += '?' + add.join('&') ;

	location.href = window.location.href.replace(/\?.*$/,'')+'#'+url;
}

function ucFirst(string) {
	if ( typeof string == 'undefined' ) return '' ;
	return string.substring(0, 1).toUpperCase() + string.substring(1);
}


// VUE COMPONENTS

Vue.component ( 'flickr-file' , {
	props : [ 'file' , 'hide_files_on_commons' ] ,
	data : function () { return { new_title:'' , new_description:'' , thumbnail_url:'' , categories:'' , flickr_description:'',
		checking_filename:true , filename_exists:false , simple_filename:true } } ,
	created : function () {
		var me = this ;
		me.new_title = flickr2commons.generateFilenameForCommons ( me.file , tt.t('default_flickr_file_name') ) ;
		me.flickr_description = $.trim ( me.file.description._content ) ;
		me.thumbnail_url = me.file.url_q ;
		if ( me.file.existing_filename_on_commons == '' ) me.checkFilenameOnCommons() ;
		me.checkSimpleFilename() ;
	} ,
	mounted : function () {
		var me = this ;
		tt.updateInterface(me.$el) ;
		$(me.$el).find("img.lazyload").lazyload();
		me.onCheckboxChange() ;
	} ,
	updated : function () {
		var me = this ;
		tt.updateInterface(me.$el) ;
		me.onCheckboxChange() ;
	} ,
	methods : {
		checkSimpleFilename : function () {
			var me = this ;
			me.simple_filename = false ;
			if ( /^[0-9\(\) ]*(\.[a-z]+){0,1}$/i.test(me.new_title) ) me.simple_filename = true ;
		} ,
		checkFilenameOnCommons : function () {
			var me = this ;
			var fn = me.new_title ;
			fn = fn.replace(/_/g,' ') ;
			if ( typeof commons_filename_cache[me.new_title] != 'undefined' ) {
				me.checking_filename = false ;
				me.filename_exists = commons_filename_cache[me.new_title] ;
				if ( me.filename_exists ) me.file.checked = false ; // Checkbox is about to be hidden
				me.checkSimpleFilename() ;
				return ;
			}
			me.checking_filename = true ;
			$.getJSON ( 'https://commons.wikimedia.org/w/api.php?callback=?' , {
				action:'query',
				titles:'File:'+fn,
				prop:'imageinfo',
				format:'json'
			} , function ( d ) {
				if ( me.new_title != fn ) return ; // Outdated query
				me.filename_exists = false ;
				if ( typeof d.query != 'undefined' && typeof d.query.pages != 'undefined' ) {
					$.each ( d.query.pages , function ( k , v ) {
						if ( k != -1 ) me.filename_exists = true ;
					} ) ;
				}
				commons_filename_cache[fn] = me.filename_exists ;
				if ( me.filename_exists ) me.file.checked = false ; // Checkbox is about to be hidden
				me.checkSimpleFilename() ;
				me.checking_filename = false ;
				me.onCheckboxChange() ;
			} ) ;
		} ,
		onCheckboxChange : function () {
			this.$emit('checkboxchanged');
		}
/*
		doesFileExistsOnCommons : function ( callback ) {
			var me = this ;
			$.getJSON ( 'https://commons.wikimedia.org/w/api.php?callback=?' , {
				action:'query',
				prop:'extlinks',
				ellimit:'500',
				generator:'search',
				gsrnamespace:6,
				gsrlimit:500,
				format:'json',
				gsrsearch:'flickr ' + me.file.id
			} , function ( d ) {
				var params = [] ;
				var patt = new RegExp("flickr\.com\/photos\/.*\/"+me.file.id+"\/{0,1}$");
				$.each ( ((d.query||{}).pages||[]) , function ( page_id , page ) {
					$.each ( page.extlinks , function ( dummy , hit ) {
						var url = hit['*'] ;
						if ( !patt.test ( url ) ) return ;
						params.push ( page.title.replace(/^File:/,'') ) ;
						me.is_checked = false ;
						return false ;
					} ) ;
				} ) ;
				me.checked_commons = true ;
				callback ( params ) ;
			} ) ;
		}
*/
	} ,
	template : '#flickr-file-template'
} ) ;



var MainPage = Vue.extend ( {
	props : [ '_user' , '_photoset' , '_group' , '_photo' , '_url' ] ,
	data : function () { return { is_authorized:false , checking_auth:false , last_error:'' , url:'' , last_message:'' , running:false , files:[] , has_run:false , tags:{} ,
		which_files:'all' , selected_tag:'' , prefix_string:'' , add2every_desc:'' , append_everywhere:'' , insert_before_license:'' , no_auto_desc:false , hide_files_on_commons:true ,
		user:'' , photoset:'' , group:'' , photo:'' , tag:'' , max_pictures:'' , owners:{} , on_commons:0 , currently_selected:0 , filename_exists_on_commons:0 , transfers_running:0 , stop_transfers:false , form_is_visible:true , parsed_user_url:'' , flickr_key_override:'' , transfer_queue:[]
	} } ,
	created : function () {
		var me = this ;
		var do_run = false ;
		me.max_pictures = flickr2commons.default_max_photos ;
		if ( typeof me.$route.query.tag != 'undefined' ) me.tag = me.$route.query.tag ;
		$.each ( [ 'user' , 'photoset' , 'group' , 'photo' , 'url' ] , function ( k , v ) {
			if ( typeof me['_'+v] == 'undefined' ) return ;
			me[v] = me['_'+v] ;
			do_run = true ;
		} ) ;
		me.checkLogin() ;
		if ( do_run ) me.doRun() ;
	} ,
	mounted : function () { tt.updateInterface(this.$el) } ,
	updated : function () { tt.updateInterface(this.$el) } ,
	methods : {
		updateCurrentlySelected : function () {
			var me = this ;
			me.currently_selected = me.files.filter ( function ( f ) { return f.checked ; } ).length ;
			me.filename_exists_on_commons = $('img.filename_exists_on_commons').length ;
			me.on_commons = $('div.file_on_commons').length ;
		} ,
		getFileIDsByTagSelection : function () {
			var me = this ;
			var ret = [] ;
			if ( me.which_files == 'all' ) {
				$.each ( me.files , function ( k , v ) { ret.push(k) } ) ;
				return ret ;
			}
			if ( me.which_files == 'with' ) {
				$.each ( me.tags[me.selected_tag] , function ( k , v ) { ret.push(v) } ) ;
				return ret ;
			}
			if ( me.which_files == 'without' ) {
				$.each ( me.files , function ( k , v ) {
					if ( -1 !== $.inArray(k,me.tags[me.selected_tag]) ) return ;
					ret.push(k) ;
				} ) ;
				return ret ;
			}
			console.log ( "BAD MODE: " + me.which_files ) ;
		} ,
		onAutoCats : function ( new_state ) {
			var me = this ;
			var file_ids = me.getFileIDsByTagSelection() ;
			$.each ( file_ids , function ( dummy , num ) {
				var id = me.files[num].id ;
				$('#file_autocats_'+id).prop('checked', new_state);
			} ) ;
			me.updateCurrentlySelected() ;
		} ,
		doSelectAll : function () {
			var me = this ;
			var file_ids = me.getFileIDsByTagSelection() ;
			$.each ( file_ids , function ( dummy , num ) {
				var id = me.files[num].id ;
				var cb = document.getElementById('file_cb_'+id) ; // Only exists (and is settable) if the checkbox is actually shown
				if ( !cb || cb.checked ) return ;
				cb.checked = true ;
				cb.dispatchEvent ( new Event('change') ) ; // Keep file.checked (v-model) in sync
			} ) ;
		} ,
		doDeselectAll : function () {
			var me = this ;
			var file_ids = me.getFileIDsByTagSelection() ;
			$.each ( file_ids , function ( dummy , num ) {
				var id = me.files[num].id ;
				var cb = document.getElementById('file_cb_'+id) ;
				if ( !cb || !cb.checked ) return ;
				cb.checked = false ;
				cb.dispatchEvent ( new Event('change') ) ; // Keep file.checked (v-model) in sync
			} ) ;
		} ,
		doPrefix : function () {
			var me = this ;
			var file_ids = me.getFileIDsByTagSelection() ;
			$.each ( file_ids , function ( dummy , num ) {
				var id = me.files[num].id ;
				var name = $('#filename_'+id).val() ;
				name = $.trim(me.prefix_string) + ' ' + $.trim(name) ;
				$('#filename_'+id).val(name) ; // TODO FIXME trigger name check
				$('#filename_'+id).get(0).dispatchEvent(new Event('input')) ;
				$('#filename_'+id).get(0).dispatchEvent(new Event('keyup')) ;
			} ) ;
			me.updateCurrentlySelected() ;
		} ,
		// Like tt.t(key), but never blank/red-debug-text for a not-yet-translated key: falls back to
		// fallback_text instead. tt.t() can't be used directly for that because with highlight_missing
		// enabled it returns a "<span style=...>key</span>" debug marker (a truthy string) for any key
		// missing from every loaded language, so a simple `tt.t(key) || fallback_text` can't detect that case.
		ttOrDefault : function ( key , fallback_text ) {
			if ( typeof tt == 'undefined' ) return fallback_text ;
			var cache = tt.translation_cache[tt.language] ;
			if ( typeof cache == 'undefined' || typeof cache[key] == 'undefined' ) return fallback_text ;
			return cache[key] ;
		} ,
		logError : function ( msg ) {
			var me = this ;
			me.running = false ;
			me.last_message = '' ;
			me.last_error = msg ;
			console.error ( msg ) ;
		} ,
		checkLogin : function () {
			var me = this ;
			me.checking_auth = true ;
			flickr2commons.checkAuth ( function ( error ) {
				me.checking_auth = false ;
				me.is_authorized = flickr2commons.is_authorized ;
				tt.updateInterface(me.$el) ;
				$('#url_input').focus() ;
			} ) ;
		} ,
		getFlickrFiles : function ( params ) {
			var me = this ;
			var max_pictures = parseInt(me.max_pictures) || flickr2commons.default_max_photos ;
			flickr2commons.getFlickrFiles ( params , 1 , max_pictures , me.tag , function ( d ) {
				if ( d.status == 'RUNNING' ) {
					me.last_message = d.so_far + ' files found so far (' + parseInt(d.page*100/d.pages) + '% done)' ;
					return ;
				}
				if ( d.status == 'ERROR' ) {
					return me.logError ( d.error ) ;
				}
				me.files = [] ;
				$.each ( d.results , function ( k , v ) {
					var file = me.completeFileProperties(v) ;
					me.addFileToList ( file ) ;
				} ) ;
				me.finishFileLoad () ;
			} ) ;
		} ,
		rewritePhotoProperties : function ( p ) {
			var tags = [] ;
			$.each ( p.tags.tag , function ( k , v ) {
				tags.push ( v._content ) ;
			} ) ;
			return {
				id:p.id,
				title:p.title._content,
				license:p.license,
				description:p.description,
				tags:tags.join(' '),
				url_o:p.sizes.size[p.sizes.size.length-1].source,
				url_q:p.sizes.size[1].source,
				originalformat:p.originalformat,
				owner:p.owner.nsid
			}
		} ,
		completeFileProperties : function ( f ) {
			f.page = 'https://www.flickr.com/photos/' + f.owner + '/' + f.id + '/' ;
			f.f2c_status = 'READY' ;
			f.existing_filename_on_commons = '' ;
			f.checked = false ;
			f.error_message = '' ;
			return f ;
		} ,
		checkProposedCommonsFilenames : function ( callback ) {
			var me = this ;
			var filenames = [] ;
			$.each ( me.files , function ( id , file ) {
				var filename = flickr2commons.generateFilenameForCommons ( file , tt.t('default_flickr_file_name') ) ;
				filenames.push ( filename ) ;
			} ) ;
			$.post ( 'api.php' , {
				action : 'check_existing_commons_filenames' ,
				filenames : JSON.stringify(filenames)
			} , function ( d ) {
				$.each ( me.files , function ( id , file ) {
					var filename = flickr2commons.generateFilenameForCommons ( file , tt.t('default_flickr_file_name') ) ;
					var filename_api = ucFirst(filename.replace(/ /g,'_')) ; // Compatability with API results
					if ( typeof d.data.files[filename_api] == 'undefined' ) {
						console.log ( "FILENAME FILE: "+filename ) ;
						return ;
					}
					commons_filename_cache[filename] = d.data.files[filename_api] ;
				} ) ;
				callback() ;
			} , 'json' )
			
		} ,
		checkFlickrFilesOnCommons : function () {
			var me = this ;
			var data = { files:{} , owners:me.owners } ;
			$.each ( me.owners , function ( nsid , name ) {
				data.files[nsid] = [] ;
			} ) ;
			$.each ( me.files , function ( id , file ) {
				var nsid = file.owner ;
				data.files[nsid].push ( file.id ) ;
			} ) ;

			me.last_message = "Checking which files are already on Commons" ;
			$.post ( './api.php' , {
				action:'check_flickr_files_in_commons',
				data:JSON.stringify(data)
			} , function ( d ) {
				$.each ( me.files , function ( dummy , file ) {
					if ( typeof d.data.files[file.id] == 'undefined' ) return ;
					file.existing_filename_on_commons = d.data.files[file.id] ;
				} ) ;
				me.which_files = 'all' ;
				me.last_message = '' ;
				me.running = false ;
				me.has_run = true ;
				me.updateCurrentlySelected() ;
			} , 'json' ) ;

		} ,
		finishFileLoad : function () {
			var me = this ;

			// Get tags 2 files
			me.selected_tag = '' ;
			var tags = {} ;
			$.each ( me.files , function ( id , file ) {
				if ( typeof file.tags == 'undefined' ) return ;
				$.each ( file.tags.split(/\s+/) , function ( k , tag ) {
					tag = $.trim ( tag.toLowerCase() ) ;
					if ( typeof tags[tag] == 'undefined' ) tags[tag] = [] ;
					tags[tag].push ( id ) ;
				} ) ;
			} ) ;
			$.each ( tags , function ( tag , files ) { // Remove tags that are in every file, what's the point in showing that?
				if ( files.length < me.files.length ) return ;
				delete tags[tag] ;
			} ) ;
			$.each ( tags , function ( tag , files ) {
				me.selected_tag = tag ;
				return false ;
			} ) ;
			me.tags = tags ;

			// Get owners
			me.owners = {} ;
			var running = 0 ;
			function fin ( person ) {
				running-- ;
				if ( typeof person != 'undefined' ) {
					me.owners[person.nsid] = [ person.nsid ] ;
					if ( typeof person.username != 'undefined' && person.username != person.nsid ) me.owners[person.nsid].push ( person.username._content ) ;
					if ( typeof person.path_alias != 'undefined' && person.path_alias != '' ) me.owners[person.nsid].push ( person.path_alias ) ;
				}
				if ( running > 0 ) return ;
				me.checkFlickrFilesOnCommons() ;
			}

			running++ ;
			me.checkProposedCommonsFilenames ( fin ) ;

			$.each ( me.files , function ( id , file ) {
				me.owners[file.owner] = file.owner ;
			} ) ;
			$.each ( me.owners , function ( nsid , dummy ) {
				running++ ;
				flickr2commons.getUserInfo ( nsid , fin ) ;
			} ) ;
		} ,
		addFileToList : function ( file ) {
			var me = this ;
			if ( typeof flickr2commons.licenses[file.license] == 'undefined' ) return ; // Bad license
			me.files.push ( file ) ;
		} ,
		doRunPhotos : function ( photos ) {
			var me = this ;
			var running = 0 ;
			function fin () {
				if ( --running > 0 ) return ;
				me.finishFileLoad() ;
			}
			router.push ( '/photo/'+photos.join(',')+me.getRouteParams() ) ;
			$.each ( photos , function ( dummy , photo_id ) {
				running++ ;
				var photo ;
				var sizes ;
				function fin2() {
					if ( typeof photo == 'undefined' || typeof sizes == 'undefined' ) return ;
					photo.sizes = sizes ;
					var file = me.completeFileProperties ( me.rewritePhotoProperties ( photo ) ) ;
					me.addFileToList ( file ) ;
					fin() ;
				}
				flickr2commons.getFileInfoFromFlickr ( {photo_id:photo_id} , function ( d ) {
					if ( typeof d.photo == 'undefined' ) return fin() ;
					photo = d.photo ;
					fin2() ;
				} ) ;
				flickr2commons.getFileSizes ( {photo_id:photo_id} , function ( d2 ) {
					if ( typeof d2.sizes == 'undefined' ) return fin() ;
					sizes = d2.sizes ;
					fin2() ;
				} ) ;
			} ) ;
		} ,
		doRunUser : function ( user ) {
			var me = this ;
			function runForUserID ( user_id ) {
				if ( user_id == '' ) return me.logError ( "No such user: "+user ) ;
				router.push ( '/user/'+user_id+me.getRouteParams() ) ;
				var params = {
					method : 'flickr.photos.search' ,
					result_key : 'photos' ,
					user_id : user_id
				} ;
				me.getFlickrFiles ( params ) ;
			}
			if ( me.parsed_user_url != '' ) {
				flickr2commons.lookupUserFromURL ( me.parsed_user_url , function ( user_id ) {
					me.parsed_user_url = '' ;
					if ( typeof user_id != 'undefined' && user_id != '' ) return runForUserID ( user_id ) ;
					flickr2commons.resolveUsername ( user , runForUserID ) ;
				} ) ;
			} else {
				flickr2commons.resolveUsername ( user , runForUserID ) ;
			}
		} ,
		doRunPhotoset : function ( photoset_id ) {
			var me = this ;
			router.push ( '/photoset/'+photoset_id+me.getRouteParams() ) ;
			var params = {
				method : 'flickr.photosets.getPhotos' ,
				result_key : 'photoset' ,
				privacy_filter : 1 ,
				photoset_id : photoset_id
			} ;
			me.getFlickrFiles ( params ) ;
		} ,
		doRunGroup : function ( group_id ) {
			var me = this ;
			flickr2commons.resolveGroupName ( group_id , function ( real_group_id ) {
				if ( group_id == '' ) return me.logError ( "No such group: "+group_id ) ;
				router.push ( '/group/'+real_group_id+me.getRouteParams() ) ;
				var params = {
					method : 'flickr.groups.pools.getPhotos' ,
					result_key : 'photos' ,
					group_id : real_group_id
				} ;
				me.getFlickrFiles ( params ) ;
			} ) ;
		} ,
		getRouteParams : function () {
			var me = this ;
			var params = [] ;
			if ( me.tag != '' ) params.push ( 'tag='+encodeURIComponent(me.tag) ) ;
			if ( params.length == 0 ) return '' ;
			return '?' + params.join('&') ;
		} ,
		parseURL : function () {
			var me = this ;
			var m ;
			if ( (m=me.url.match(/\/photos\/[^\/]+\/(\d+)/)) != null ) me.photo = m[1] ;
			else if ( (m=me.url.match(/\/albums\/(\d+)/)) != null ) me.photoset = m[1] ;
			else if ( (m=me.url.match(/\/groups\/([^\/]+)/)) != null ) me.group = m[1] ;
			else if ( (m=me.url.match(/\/people\/([^\/]+)/)) != null ) me.user = m[1] ;
			else if ( (m=me.url.match(/\/photos\/([^\/?#]+)/)) != null ) {
				me.user = m[1] ;
				me.parsed_user_url = me.url ;
			}
			else return ;
			me.url = '' ;
		} ,
		doRun : function () {
			var me = this ;
			me.last_message = '' ;
			me.last_error = '' ;
			if ( me.checking_auth ) {
				setTimeout ( function () {me.doRun()} , 200 ) ;
				return ;
			}
			if ( !me.is_authorized ) return me.logError ( "Not authorized" ) ;
			flickr2commons.flickr_api_key = $.trim(me.flickr_key_override) || flickr2commons.default_flickr_api_key ;
			if ( me.url != '' ) me.parseURL() ;
			me.last_message = 'Running...' ;
			me.running = true ;
			me.files = [] ;
			me.currently_selected = 0 ;
			me.transfers_running = 0 ;
			me.stop_transfers = false ;
			me.form_is_visible = false ;
			if ( me.user != '' ) me.doRunUser(me.user) ;
			else if ( me.photoset != '' ) me.doRunPhotoset(me.photoset) ;
			else if ( me.group != '' ) me.doRunGroup(me.group) ;
			else if ( me.photo != '' ) me.doRunPhotos(me.photo.split(',')) ;
			else return me.logError ( tt.t('nothing2work_with') ) ;
		} ,
		onHideCommonsChange : function () {
			setTimeout ( function () {
				window.scroll(0,1) ;
				window.scroll(0,-1) ;
			} , 10 ) ;
		} ,
		stopTransfers : function () {
			this.stop_transfers = true ; // transferNext() checks this before pulling each new item off the queue
		} ,
		// Bounded worker-pool: build the queue once, then run up to max_concurrent_transfers
		// workers that each pull the next item as soon as they finish. No polling, no silent
		// dead-ends - every path either continues the pipeline or checks for completion.
		transferAll : function () {
			var me = this ;
			me.transfer_queue = me.files.filter ( function ( file ) {
				return file.checked && ( file.f2c_status == 'READY' || file.f2c_status == 'ERROR' ) ;
			} ) ;
			if ( me.transfer_queue.length == 0 ) return ;
			me.stop_transfers = false ;
			$("div.flickr-file :input").attr("disabled", true) ;
			var workers = Math.min ( max_concurrent_transfers , me.transfer_queue.length ) ;
			for ( var i = 0 ; i < workers ; i++ ) me.transferNext() ;
		} ,
		transferNext : function () {
			var me = this ;
			if ( me.stop_transfers || me.transfer_queue.length == 0 ) {
				if ( me.transfers_running == 0 ) {
					me.stop_transfers = false ;
					$("div.flickr-file :input").attr("disabled", false) ;
				}
				return ;
			}
			var file = me.transfer_queue.shift() ;
			file.checked = false ; // No longer "selected" - it's now in progress
			file.error_message = '' ;
			me.transfers_running++ ;
			me.transferOne ( file , function () {
				me.transfers_running-- ;
				me.transferNext() ; // Pull the next queued item into this now-free worker slot
			} ) ;
		} ,
		transferOne : function ( file , done ) {
			var me = this ;

			var o = {
				photo : { id : file.id } ,
				auto_categories : false ,
				best_size : { source : file.url_o } ,
				toolname : flickr2commons.toolname ,
				error : ''
			} ;

			var file_node = $('div[flickr_file_id="'+file.id+'"]') ;
			o.filename_on_commons = $.trim(file_node.find('input.new_filename').val()) ;
			while ( o.filename_on_commons.length > 250 ) o.filename_on_commons.replace ( /.(\.[a-z]+)$/i , '$1' ) ;
			o.new_desc = $.trim(file_node.find('textarea.file_description').val()) ;
			if ( o.new_desc == '' && !me.no_auto_desc ) o.new_desc = $.trim(file.description._content) ;
			if ( $.trim(me.add2every_desc) != '' ) o.new_desc = $.trim ( o.new_desc + "\n" + $.trim(me.add2every_desc) ) ;

			var categories = $.trim(file_node.find('textarea.categories').val()).split("\n") ;
			if ( categories.join('') != '' ) o.auto_categories = false ;
			else o.auto_categories = file_node.find('input.auto_categories').is(':checked') ;

			o.insert_before_license = me.insert_before_license ;

			file.f2c_status = 'TRANSFER' ;
			flickr2commons.generateInformationTemplate ( o , function ( o ) {
				if ( o.error != '' ) {
					file.f2c_status = 'ERROR' ;
					file.error_message = o.error ;
					console.error ( 'generateInformationTemplate failed for', o.photo.id, ':', o.error ) ;
					return done() ;
				}

				// Complete description
				$.each ( categories , function ( dummy , category ) {
					category = $.trim(category) ;
					if ( category == '' ) return ;
					o.information_template += "\n[[Category:" + ucFirst(category) + "]]" ;
				} ) ;
				if ( $.trim(me.append_everywhere)!='' ) o.information_template = $.trim(o.information_template+"\n"+$.trim(me.append_everywhere)) ;

				flickr2commons.uploadFileToCommons ( o , function ( o ) {
					if ( o.error=='' ) {
						file.f2c_status = 'DONE' ;
						file.existing_filename_on_commons = o.filename_on_commons ;
					} else {
						file.f2c_status = 'ERROR' ;
						file.error_message = o.error ;
						console.error ( 'Transfer failed for', o.filename_on_commons, ':', o.error ) ;
					}

					// Logging
					if ( flickr2commons.enable_upload_logging ) {
						$.getJSON ( 'https://tools.wmflabs.org/magnustools/logger.php?callback=?' , { tool:flickr2commons.toolname , method:'upload to commons' } , function(j){} ) ;
					}

					done() ;
				} ) ;
			} ) ;
		}
	} ,
	template : '#main-page-template'
} ) ;


// GLOBAL/MAIN

const routes = [
  { path: '/', component: MainPage },
  { path: '/user/:_user', component: MainPage , props:true },
  { path: '/photoset/:_photoset', component: MainPage , props:true },
  { path: '/group/:_group', component: MainPage , props:true },
  { path: '/photo/:_photo', component: MainPage , props:true },
  { path: '/url/:_url', component: MainPage , props:true },
] ;

var max_concurrent_transfers = 5 ;
var commons_filename_cache = {} ;
var router ;
var app ;
var tt ;


$(document).ready ( function () {
	flickr2commons.oauth_uploader_base = 'api.php' ;
	flickr2commons.oauth_uploader_api = 'api.php?botmode=1' ;
	flickr2commons.flinfo = 'flinfo_proxy.php' ;

	var cnt = 2 ;
	function fin () {
		cnt-- ;
		if ( cnt > 0 ) return ;
		router = new VueRouter({routes}) ;
		app = new Vue ( { router } ) .$mount('#app') ;
	}

	// Load interface translations
	tt = new ToolTranslation ( {
		tool : 'flickr2commons' , // Deliberately NOT flickr2commons.toolname: this is the shared ToolTranslate dataset name from upstream, not this fork's identity
		fallback : 'en' ,
		highlight_missing : true ,
		onLanguageChange : function ( new_lang ) {
		} ,
		onUpdateInterface : function () {
		} ,
		callback : function () {
			tt.addILdropdown ( '#tooltranslate_wrapper' ) ;
			fin() ;
		}
	} ) ;

	// Load metadata
	$.get ( 'api.php' , {
		meta:'all',
		action:'get_flickr_key'
	} , function ( d ) {
		if ( typeof d.data == 'object' ) {
			flickr2commons.flickr_api_key = d.data.flickr_key ;
			flickr2commons.default_max_photos = d.data.max_photos || 500 ;
			flickr2commons.enable_upload_logging = !!d.data.enable_upload_logging ;
		} else {
			flickr2commons.flickr_api_key = d.data ;
			flickr2commons.default_max_photos = 500 ;
			flickr2commons.enable_upload_logging = false ;
		}
		flickr2commons.default_flickr_api_key = flickr2commons.flickr_api_key ;
		fin() ;
	} , 'json' ) ;
} ) ;
