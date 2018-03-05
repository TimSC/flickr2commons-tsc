// ENFORCE HTTPS
if (location.protocol != 'https:') location.href = 'https:' + window.location.href.substring(window.location.protocol.length);

Vue.component ( 'flickr-file' , {
	props : [ 'file' ] ,
	data : function () { return { new_title:'' , new_description:'' , is_checked:true , exists_on_commons:false , checked_commons:false , existing_commons_filename:'' , checking_filename:false , filename_exists:false , filename_cache:{} } } ,
	created : function () {
		var me = this ;
		me.new_title = me.sanitizeTitle ( me.file.title ) ;
		me.new_description = me.file.description._content ;
		me.doesFileExistsOnCommons ( function ( d ) {
			me.checkFilenameOnCommons() ;
			if ( d.length == 0 ) return ;
			me.existing_commons_filename = d[0] ;
		} ) ;
	} ,
	mounted : function () { tt.updateInterface(this.$el) } ,
	updated : function () { tt.updateInterface(this.$el) } ,
	methods : {
		sanitizeTitle : function ( t ) {
			var me = this ;
			t = t.replace ( /_/g , ' ' ) ;
			t = t.replace ( /[\:\/\|]/g , ' ' ) ;
			t = t.replace ( /\s+/g , ' ' ) ;
			t = $.trim ( t ) ;
			t = t.replace ( /\.(JPG|JPEG|PNG|TIF|TIFF)$/i , '' ) ;
			if ( t.length > 230 ) t = t.substr ( 0 , 230 ) ;
			t += " (" + me.file.id + ")" ;
			t += '.' + me.file.originalformat.toLowerCase() ;
			return t ;
		} ,
		checkFilenameOnCommons : function () {
			var me = this ;
			var fn = me.new_title ;
			if ( typeof me.filename_cache[me.new_title] != 'undefined' ) {
				me.filename_exists = me.filename_cache[me.new_title] ;
				return ;
			}
			me.checking_filename = true ;
			$.getJSON ( 'https://commons.wikimedia.org/w/api.php?callback=?' , {
				action:'query',
				titles:'File:'+me.new_title,
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
				me.filename_cache[me.new_title] = me.filename_exists ;
				me.checking_filename = false ;
			} ) ;
		} ,
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
				gsrsearch:'flickr photos ' + me.file.id
			} , function ( d ) {
				var params = [] ;
				var patt = new RegExp("flickr\.com\/photos\/.*\/"+me.file.id+"\/{0,1}$");
				$.each ( ((d.query||{}).pages||[]) , function ( page_id , page ) {
					$.each ( page.extlinks , function ( dummy , hit ) {
						var url = hit['*'] ;
						if ( !patt.test ( url ) ) return ;
						params.push ( page.title.replace(/^File:/,'') ) ;
						me.exists_on_commons = true ;
						me.is_checked = false ;
						return false ;
					} ) ;
				} ) ;
				me.checked_commons = true ;
				callback ( params ) ;
			} ) ;
		}
	} ,
	template : '#flickr-file-template'
} ) ;



var MainPage = Vue.extend ( {
	props : [ '_user' , '_photoset' , '_group' , '_photo' , '_tag' , '_max_pictures' ] ,
	data : function () { return { is_authorized:false , checking_auth:false , last_error:'' , last_message:'' , running:false , files:[] , has_run:false , tags:{} , which_files:'all' , selected_tag:'' , prefix_string:'' ,
		user:'' , photoset:'' , group:'' , photo:'' , tag:'' , max_pictures:''
	} } ,
	created : function () {
		var me = this ;
		var do_run = false ;
		$.each ( [ 'user' , 'photoset' , 'group' , 'photo' , 'tag' , 'max_pictures' ] , function ( k , v ) {
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
		doSelectAll : function () {
			var me = this ;
			var file_ids = me.getFileIDsByTagSelection() ;
			$.each ( file_ids , function ( dummy , num ) {
				var id = me.files[num].id ;
				$('#file_cb_'+id).prop('checked', true);
			} ) ;
		} ,
		doDeselectAll : function () {
			var me = this ;
			var file_ids = me.getFileIDsByTagSelection() ;
			$.each ( file_ids , function ( dummy , num ) {
				var id = me.files[num].id ;
				$('#file_cb_'+id).prop('checked', false);
			} ) ;
		} ,
		doPrefix : function () {
			var me = this ;
			var file_ids = me.getFileIDsByTagSelection() ;
			$.each ( file_ids , function ( dummy , num ) {
				var id = me.files[num].id ;
				var name = $('#filename_'+id).val() ;
				name = me.prefix_string + name ;
				$('#filename_'+id).val(name) ;
			} ) ;
		} ,
		logError : function ( msg ) {
			var me = this ;
			me.running = false ;
			me.last_message = '' ;
			me.last_error = msg ;
		} ,
		checkLogin : function () {
			var me = this ;
			me.checking_auth = true ;
			flickr2commons.checkAuth ( function ( error ) {
				me.checking_auth = false ;
				me.is_authorized = flickr2commons.is_authorized ;
				tt.updateInterface(me.$el) ;
			} ) ;
		} ,
		getFlickrFiles : function ( params ) {
			var me = this ;
			flickr2commons.getFlickrFiles ( params , 1 , (me.max_pictures*1) , me.tag , function ( d ) {
				if ( d.status == 'RUNNING' ) {
					me.last_message = d.so_far + ' files found so far' ;
					return ;
				}
				if ( d.status == 'ERROR' ) {
					return me.logError ( d.error ) ;
				}
				me.files = [] ;
				$.each ( d.results , function ( k , v ) {
					me.files.push ( me.completeFileProperties(v) ) ;
				} ) ;
				me.finishFileLoad () ;
			} ) ;
		} ,
		doRunUser : function ( user ) {
			var me = this ;
			flickr2commons.resolveUsername ( user , function ( user_id ) {
				if ( user_id == '' ) return me.logError ( "No such user: "+user ) ;
				var params = {
					method : 'flickr.photos.search' ,
					result_key : 'photos' ,
					user_id : user_id
				} ;
				me.getFlickrFiles ( params ) ;
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
			return f ;
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
					if ( typeof me.tags[tag] == 'undefined' ) tags[tag] = [] ;
					tags[tag].push ( id ) ;
					if ( me.selected_tag == '' ) me.selected_tag = tag ;
				} ) ;
			} ) ;
			me.tags = tags ;

			me.which_files = 'all' ;
			me.last_message = '' ;
			me.running = false ;
			me.has_run = true ;
		} ,
		doRunPhotos : function ( photos ) {
			var me = this ;
			var running = 0 ;
			function fin () {
				if ( --running > 0 ) return ;
				me.finishFileLoad() ;
			}
			$.each ( photos , function ( dummy , photo_id ) {
				running++ ;
				var photo ;
				var sizes ;
				function fin2() {
					if ( typeof photo == 'undefined' || typeof sizes == 'undefined' ) return ;
					photo.sizes = sizes ;
					me.files.push ( me.completeFileProperties ( me.rewritePhotoProperties ( photo ) ) ) ;
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
		doRun : function () {
			var me = this ;
			me.last_message = '' ;
			if ( me.checking_auth ) {
				setTimeout ( function () {me.doRun()} , 200 ) ;
				return ;
			}
			if ( !me.is_authorized ) return me.logError ( "Not authorized" ) ;
			me.last_message = 'Running...' ;
			me.running = true ;
			me.files = [] ;
			if ( me.user != '' ) me.doRunUser(me.user) ;
			else if ( me.photoset != '' ) me.doRunPhotoset(me.photoset) ;
			else if ( me.group != '' ) me.doRunGroup(me.group) ;
			else if ( me.photo != '' ) me.doRunPhotos(me.photo.split(',')) ;
			else return me.logError ( tt.t('nothing2work_with') ) ;
		}
	} ,
	template : '#main-page-template'
} ) ;



const routes = [
  { path: '/', component: MainPage },
  { path: '/user/:_user', component: MainPage , props:true },
  { path: '/photoset/:_photoset', component: MainPage , props:true },
  { path: '/group/:_group', component: MainPage , props:true },
  { path: '/photo/:_photo', component: MainPage , props:true },
] ;

var router ;
var app ;
var tt ;


$(document).ready ( function () {

	var cnt = 2 ;
	function fin () {
		cnt-- ;
		if ( cnt > 0 ) return ;
		router = new VueRouter({routes}) ;
		app = new Vue ( { router } ) .$mount('#app') ;
	}

	// Load interface translations
	tt = new ToolTranslation ( {
		tool : 'flickr2commons' ,
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
	$.get ( '/fist/file_candidates/api.php' , {
		meta:'all',
		action:'get_flickr_key'
	} , function ( d ) {
//		metadata = d.meta ;
		flickr2commons.flickr_api_key = d.data ;
		fin() ;
	} , 'json' ) ;

/*

	// Load media properties
	var sparql = 'SELECT ?property ?propertyLabel WHERE { ?property wikibase:propertyType wikibase:CommonsMedia . SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". } }' ;
	wd.loadSPARQL ( sparql , function ( json ) {
		var group2hint = {
			'diagram' : /\b(logo|seal|flag|structure|symbol|icon|diagram|plan|bathymetry)\b/ ,
			'map' : /\bmap\b/ ,
			'photo' : /\b(image|banner|view)\b/ ,
			'video' : /\bvideo\b/ ,
			'audio' : /\baudio\b/ ,
		}
		$.each ( json.results.bindings , function ( dummy , b ) {
			var label = b.propertyLabel.value ;
			var p = wd.itemFromBinding ( b.property ) ;
			media_prop2label['P'+p] = label ;
			if ( p == 18 || p == 368 || p == 369 ) return ; // Special cases
			var to_group = 'other' ;
			$.each ( group2hint , function ( group , hint ) {
				if ( !hint.test(label) ) return ;
				to_group = group ;
				return false ;
			} ) ;
			media_props[to_group].push ( { p:'P'+p , label:label } ) ;
		} )
		$.each ( media_props , function ( group , props ) {
			props.sort ( function ( a , b ) {
				return (a.label.toLowerCase()>b.label.toLowerCase())?1:-1 ;
			} ) ;
		} ) ;
		fin() ;
	} ) ;
	
	$('#navbar_search_form').submit ( function ( ev ) {
		ev.preventDefault();
		var query = $('#navbar_search_form input[type="text"]').get(0).val() ;
		console.log("1",query);
		return false ;
	} ) ;
*/
} ) ;