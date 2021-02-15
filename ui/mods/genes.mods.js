
// Scroll Within Page
g.def("mod.scrollTo", function (destination) {
  var documentHeight = Math.max(document.body.scrollHeight, document.body.offsetHeight, document.documentElement.clientHeight, document.documentElement.scrollHeight, document.documentElement.offsetHeight);
  var windowHeight = window.innerHeight || document.documentElement.clientHeight || document.getElementsByTagName('body')[0].clientHeight;
  var getOffsetTop = function (element) {
    var offsetTop = 0;
    while (element) {
      offsetTop += element.offsetTop;
      element = element.offsetParent;
    }
    return offsetTop;
  };
  var destinationOffset = typeof destination === 'number' ? destination : getOffsetTop(destination);
  var destinationOffsetToScroll = Math.round(documentHeight - destinationOffset < windowHeight ? documentHeight - windowHeight : destinationOffset);
  window.scrollTo({
    top: destinationOffsetToScroll,
    left: 0,
    behavior: 'smooth'
  });
});

// Number Formatting, EUR, USD, Currency
g.def("mod.numberFormat", function () {
  var euro_format = new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0
  });

  var usd_format = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0
  });

  var try_format = new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0
  });

  var flat_format = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0
  });

  var percent_format = new Intl.NumberFormat('en-US', {
    style: "percent",
    maximumFractionDigits: 2
  });

  var nums = g.els("u");
  for (var i = 0; i < nums.length; i++) {
    var el = nums[i];
    var format = el.dataset.format;
    var el_html = el.innerHTML;
    if (!isNaN(el_html)) {
      if (format === "EUR") {
        el.innerHTML = euro_format.format(el.innerHTML);
      } else if (format === "USD") {
        el.innerHTML = usd_format.format(el.innerHTML);
      } else if (format === "TRY") {
        el.innerHTML = try_format.format(el.innerHTML).replace("€", "₺");
      } else if (format === "PERC") {
        el.innerHTML = percent_format.format(el.innerHTML);
      } else {
        el.innerHTML = flat_format.format(el.innerHTML);
      }
    }
  }

});

// Maxlength limiting on textareas especially, and maybe count
g.def("mod.maxLength", function () {
  var els = g.els(".maxlength");
  if (g.is(els)) {
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      var elc = el.dataset.maxlength;

      var func = function () {
        var len = parseInt(elc, 10);

        if (this.value.length > len) {
          // alert('Maximum length exceeded: ' + len);
          this.value = this.value.substr(0, len);
          return false;
        }
      };

      el.onkeyup = func;
      el.onblur = func;
    }
  }
});

// Simple javascript scrollbar
g.def("mod.SimpleScrollbar", function () {
  var scrollbars = g.els(".ss_raw");
  for (i = 0; i < scrollbars.length; ++i) {
    var el = scrollbars[i];
    SimpleScrollbar.initEl(el);
  }
});

// Sortable
g.def("mod.sortable", function () {
  var sortables = g.els(".sortable");
  var options = {
    animation: 150
  };
  if (sortables.length > 0) {
    for (var i = 0; i < sortables.length; i++) {
      var sortable_item = sortables[i];
      if (sortable_item.classList.contains("dropzone_raw")) {
        options.onSort = function (event) {
          var dropzone_element = event.srcElement;
          g.run("mod.dropzoneFiles", dropzone_element);
        };
      }
      var sortable = Sortable.create(sortable_item, options);
    }
    /*
    g.oea("#root", ".sortable", function (selector, el) {
      console.log(selector);
      var sortables = g.els(selector, "#root");

      for (var i = 0; i < sortables.length; i++) {
        var sortable = Sortable.create(sortables[i], options);
      }
    });
    */
  }
});

// Muuri 
g.def("mod.muuri", function () {
  var options = {
    layout: {
      fillGaps: true,
      horizontal: false,
      alignRight: false,
      alignBottom: false,
      rounding: true
    },
    layoutOnResize: 100,
    layoutOnInit: true,
    layoutDuration: 300,
    layoutEasing: 'ease',
    sortData: {
      id: function (item, element) {
        return parseFloat(element.getAttribute('data-id'));
      }
    }
  };
  var muuris = g.els('.muuri');
  if (muuris.length > 0) {
    var muuri = new Muuri('.muuri', options);

    g.on("click", ".muuri_filter", function () {
      muuri.filter('[data-color="blue"]');
    });
    g.on("click", ".muuri_sort", function () {
      muuri.sort('id:desc');
    });
    g.on("click", ".muuri_reset", function () {
      muuri.filter('[data-color]');
      muuri.sort('id');
    });
  }
});

// Tagify 
g.def("mod.tagify", function () {
  // https://github.com/yairEO/tagify
  var tagifies = g.els(".tagify_raw");
  var options = {
    dropdown: {
      enabled: 0, // place the dropdown near the typed text
      closeOnSelect: false, // keep the dropdown open after selecting a suggestion
      highlightFirst: true
    }
  };
  for (i = 0; i < tagifies.length; ++i) {
    var element = tagifies[i];
    var data_raw = element.dataset.raw;
    var data_selected = element.dataset.selected;
    var on_tag_select = element.dataset.on_tag_select;

    if (g.hc(element, "tagify_select")) {
      options.mode = "select";
      options.dropdown.closeOnSelect = true;
    }
    else {
      delete options.mode;
      options.dropdown.closeOnSelect = false;
    }

    options.whitelist = element.value.trim().split(/\s*,\s*/);

    element.tagify = new Tagify(element, options);
    element.tagify.on('input', function (evt) {
      var obj = evt.detail;
      var el = obj.tagify.DOM.originalInput;
      var data_source = el.dataset.source;
      obj.tagify.settings.whitelist.length = 0; // reset current whitelist
      obj.tagify.loading(true).dropdown.hide.call(obj.tagify) // show the loader animation

      g.xp(data_source, {
        txt: obj.value
      }, function (response) {
        var rj = JSON.parse(response);
        // replace tagify "whitelist" array values with new values
        // and add back the ones already choses as Tags
        obj.tagify.settings.whitelist = rj.labels;
        // render the suggestions dropdown.
        obj.tagify.loading(false).dropdown.show.call(obj.tagify, obj.value);
      });
    });
    if (g.is(on_tag_select)) {
      element.tagify.on('dropdown:select', function (evt) {
        var obj = evt.detail;
        var el = obj.tagify.DOM.originalInput;
        var cb = el.dataset.on_tag_select;
        g.run(cb, evt.detail);
      });
    }
  }
});

// JSON Editor 
g.def("mod.jsonEditor", function () {
  var jsoneditors = g.els(".jsoneditor_raw");
  var options = {
    modes: ["tree", "code"],
    enableSort: false,
    enableTransform: false
  };

  for (i = 0; i < jsoneditors.length; ++i) {
    var element = jsoneditors[i];

    var form_name = element.dataset.name;
    if (g.is(form_name)) {
      var input = document.createElement('input');
      input.type = "hidden";
      input.name = form_name;
      input.id = form_name;
      element.parentNode.insertBefore(input, element.nextSibling);
      options["onChange"] = function () {
        var json = editor.get();
        input.value = JSON.stringify(json);
      };
    }
    var editor = new JSONEditor(element, options);
    element.JSONEditor = editor;
    element.JSONInput = input;

    var data_raw = element.dataset.raw;
    var data_source = element.dataset.source;
    var data_branch = element.dataset.branch;

    if (g.is(data_source)) {
      g.xg(data_source, function (response) {
        var rj = JSON.parse(response);
        if (g.is(data_branch)) {
          var rjd = g.dig(rj, data_branch);
          rj = rjd;
        }
        editor.set(rj);
      });
    } else if (g.is(data_raw)) {
      var rj = JSON.parse(data_raw);
      editor.set(rj);
    }
  }
});

// Dropzone Uploads 
g.def("mod.dropzoneProcessStored", function () {
  var dropzone, data_options, data_stored, data_uploads, ds;
  if (arguments.length === 1) {
    var dropzone_el = arguments[0];
    dropzone = dropzone_el.Dropzone;
    data_options = JSON.parse(dropzone_el.dataset.options);
    ds = dropzone_el.dataset.raw;
    data_uploads = data_options.uploads;
  } else if (arguments.length > 1) {
    data_stored = arguments[0];
    data_uploads = arguments[1];
    dropzone = arguments[2];
    ds = decodeEntities(data_stored);
  }
  var stored = JSON.parse(ds);
  var sl = stored.length;
  if (sl > 0) {
    var mockFiles = [];
    var tw = dropzone.options.thumbnailWidth;
    var th = dropzone.options.thumbnailHeight;
    var rm = dropzone.options.resizeMethod;
    var fo = dropzone.options.fixOrientation;

    for (var i = 0; i < sl; i++) {
      var img = stored[i];
      var mockFile = {
        name: img[0],
        type: img[1],
        size: img[2],
        dataURL: data_uploads + img[0],
        accepted: true,
        status: "success"
      };
      mockFiles.push(mockFile);
      dropzone.files.push(mockFile);
    }

    var drop_fnc = function (load) {
      var mf = mockFiles[load];
      dropzone.createThumbnailFromUrl(mf, tw, th, rm, fo, function (thumbnail) {
        dropzone.emit("addedfile", mf);
        dropzone.emit("complete", mf);
        dropzone.emit("success", mf);
        dropzone.emit('thumbnail', mf, thumbnail);
        var dzus = g.els(".dz-upload", dropzone.element);
        for (let i = 0; i < dzus.length; i++) {
          var dzu = dzus[i];
          dzu.style.width = '100%';
        }
        g.run("mod.dropzoneFiles", dropzone.element);
        var next = load + 1;
        if (next < sl) {
          drop_fnc(next);
        }
      });
    };
    drop_fnc(0);

    last_dropzone = dropzone;
  }
});

function htmlEntities(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function decodeEntities(input) {
  var y = document.createElement('textarea');
  y.innerHTML = input;
  return y.value;
}

g.def("mod.dropzoneFiles", function (dropzone_element) {
  var curr_file_names_input = g.el(".dropzone_files", dropzone_element);
  var curr_dropzone_files = dropzone_element.dropzone.files;

  //var curr_dropzone_files = args[0];
  //var curr_file_names_input = args[1];

  var upload_success_previews = g.els(".dz-success", dropzone_element);
  var file_names = [];

  //console.log(curr_dropzone_files);
  //console.log(upload_success_previews);

  for (var i = 0; i < upload_success_previews.length; i++) {
    var upload_success = upload_success_previews[i];
    var file_name_div = g.el(".dz-filename", upload_success);
    var file_name_span = g.el("span", file_name_div);
    file_names.push(file_name_span.innerHTML);
  }
  curr_file_names_input.value = JSON.stringify(file_names);
});

var last_dropzone = null;
g.def("mod.dropzone", function () {
  var dropzones = g.els(".dropzone_raw");
  var options = {
    url: "",
    paramName: "uploads",
    parallelUploads: 10,
    maxFilesize: 2,
    maxFiles: 10,
    thumbnailWidth: 240,
    thumbnailHeight: 180,
    resizeMethod: "crop",
    acceptedFiles: "image/*,application/pdf",
    filesizeBase: 1000,
    uploadMultiple: true,
    // previewTemplate: document.querySelector('#preview').innerHTML,
    addRemoveLinks: true,
    timeout: 10000,
    autoProcessQueue: false,
    dictRemoveFile: "&times;",
    // dictFileTooBig: 'Image is larger than 16MB',
    init: function () {
      var curr_dropzone = this;
      g.on("click", ".dropzone_upload_queue", function () {
        curr_dropzone.processQueue();
      });
      g.on("click", ".dropzone_clear_queue", function () {
        curr_dropzone.removeAllFiles(true);
      });
      curr_dropzone.on("addedfile", function (file) {
        // var s = file.size; // 45894
        var t = file.type; // "application/pdf"
        var preview_el = file.previewElement;
        var label_el = g.el(".dz-size", preview_el);
        label_el.innerHTML += '<span class="dz-file-type">' + t + '</span>';
        // console.log(curr_dropzone.options);
      });
      curr_dropzone.on("thumbnail", function (file) {
        var w = file.width;
        var h = file.height;
        var preview_el = file.previewElement;
        var label_el = g.el(".dz-size", preview_el);
        label_el.innerHTML += '<span class="dz-file-dimensions">' + w + "x" + h + '</span>';
        // file.rejectDimensions();
        // file.acceptDimensions();
      });
      curr_dropzone.on("successmultiple", function (files, responseText) {
        for (var i = 0; i < files.length; i++) {
          var file = files[i];
          if (g.is(responseText.meta.msgs[file.name])) {
            var error = responseText.meta.msgs[file.name][0];
            var preview_el = file.previewElement;
            preview_el.classList.add("dz-error");
            preview_el.classList.remove("dz-success");
            _ref = preview_el.querySelectorAll("[data-dz-errormessage]");
            _results = [];
            for (_i = 0, _len = _ref.length; _i < _len; _i++) {
              node = _ref[_i];
              _results.push(node.textContent = error);
            }
          }
        }

        g.run("mod.dropzoneFiles", curr_dropzone.element);
      });
      curr_dropzone.on("removedfile", function (file) {
        // var files = curr_dropzone.files;
        // for (var i = 0; i < files.length; i++) { var cd_file = files[i]; }
        if (file.status == "success") {
          var delete_url = curr_dropzone.options.delete_url;
          var post_data = {
            "delete_file": file.name
          };
          g.xp(delete_url, post_data, function (response) {
            // console.log(response);
          });

          g.run("mod.dropzoneFiles", curr_dropzone.element);
        }
      });
    },
    clickable: ".dropzone_queue_files"
  };

  if (dropzones.length > 0) {
    Dropzone.autoDiscover = false;
  }

  for (i = 0; i < dropzones.length; ++i) {
    var element = dropzones[i];
    g.ac(element, "dropzone");
    var data_options = JSON.parse(element.dataset.options);
    var data_stored = data_options.stored;
    var data_uploads = data_options.uploads;
    for (var attr in data_options) {
      options[attr] = data_options[attr];
    }
    var dropzone = new Dropzone(element, options);
    element.Dropzone = dropzone;

    if (g.is(data_stored)) {
      g.run("mod.dropzoneProcessStored", data_stored, data_uploads, dropzone);
    }
  }
  /*
    https://www.dropzonejs.com/#usage
    https://gitlab.com/meno/dropzone/-/wikis/faq#how-to-add-a-button-to-remove-each-file-preview
    https://gitlab.com/meno/dropzone/-/tree/master#L464
  */
});

// Quill Rich Text Editor 
g.def("mod.quill", function () {
  var quills = g.els(".quill");
  var options = {
    modules: {},
    theme: 'bubble'
  };

  for (i = 0; i < quills.length; ++i) {
    var element = quills[i];
    var theme = element.dataset.quill;
    var form_name = element.dataset.name;
    if (g.is(form_name)) {
      var input = document.createElement('input');
      input.type = "hidden";
      input.name = form_name;
      input.id = form_name;
      element.parentNode.insertBefore(input, element.nextSibling);
    }

    if (g.is(theme)) {
      options.theme = theme;
      if (theme == "snow") {
        var toolbarContainer = [
          ['bold', 'italic', 'underline', 'strike', 'link'], // toggled buttons
          ['blockquote', 'code-block'],

          ['video', 'image'],

          [{
            'header': 1
          }, {
            'header': 2
          }], // custom button values
          [{
            'list': 'ordered'
          }, {
            'list': 'bullet'
          }],
          [{
            'script': 'sub'
          }, {
            'script': 'super'
          }], // superscript/subscript
          [{
            'indent': '-1'
          }, {
            'indent': '+1'
          }], // outdent/indent
          [{
            'direction': 'rtl'
          }], // text direction

          [{
            'size': ['small', false, 'large', 'huge']
          }], // custom dropdown
          [{
            'header': [1, 2, 3, 4, 5, 6, false]
          }],

          [{
            'color': []
          }, {
            'background': []
          }], // dropdown with defaults from theme
          [{
            'font': []
          }],
          [{
            'align': []
          }],

          ['clean'],
          ['showHtml']
        ];
        var toolbarHandlers = {
          showHtml: function () {
            var curr_editor = this.quill;

            var delta = curr_editor.getContents();
            var text = curr_editor.getText();
            var justHtml = curr_editor.root.innerHTML;

            console.log(justHtml);
          }
          /*
          
          showHtml: function (value) {
            if (value) {
              var href = prompt('Enter the URL');
              this.quill.format('link', href);
            } else {
              this.quill.format('link', false);
            }
          }

          */
        };
        var toolbarOptions = {
          container: toolbarContainer,
          handlers: toolbarHandlers
        };
        options.modules.imageResize = {
          displaySize: true
        };
        options.modules.toolbar = toolbarOptions;
      }
    }
    var editor = new Quill(element, options);
    element.quill = editor;

    if (g.is(form_name)) {
      editor.on('text-change', function (delta, oldDelta, source) {
        var editor_value = editor.root.innerHTML;
        input.value = editor_value;
      });
    }
    if (theme == "snow") {
      //var showHtmlEl = g.el(".ql-showHtml");
      //showHtmlEl.innerHTML = 'HTML';
    }
    // https://github.com/quilljs/quill/issues/2611
  }
  // g.oc(".ql-showHtml", function () {
  //   alert("showHtmlButton");
  /*
  quill.focus();
  var range = quill.getSelection();  
  quill.insertText(range.index, ' ¯\_(ツ)_/¯ ', 'bold', false); 
  quill.setSelection(range.index + 10);
  console.log(e.target.value);
  */
  // });
});

g.def("mod.SimpleBodyBGRandomizer", function () {
  var bgs_randomize = g.el(".bgs_randomize");
  if (g.is(bgs_randomize)) {
    var bgs = g.el(".bgs");
    if (!g.is(bgs)) { bgs = g.el("body"); }
    g.ac(bgs, "bgi" + (Math.floor(Math.random() * 42) + 1));
  }
});

g.que("on_load.mods", function () {
  g.on("click", ".scroll_to", function (el) {
    var target_scroll_selector = el.dataset.scroll;
    var target_scroll = g.el(target_scroll_selector);
    g.run("mod.scrollTo", target_scroll);
  });
  g.run("mod.SimpleBodyBGRandomizer");
  g.run("mod.numberFormat");
  g.run("mod.maxLength");
  g.run("mod.SimpleScrollbar");
  g.run("mod.jsonEditor");
  g.run("mod.tagify");
  g.run("mod.quill");
  g.run("mod.sortable");
  g.run("mod.dropzone");
  g.run("mod.muuri");
  //g.on("click", "add_new_sortable", function () {
  //  var pp = g.el(".content");
  //  pp.innerHTML += '<ul id="zozo" class="sortable mt1r"><li>List Item Number 1</li><li>List Item Number 2</li><li>List Item Number 3</li><li>List Item Number 4</li></ul>';
  //});
});