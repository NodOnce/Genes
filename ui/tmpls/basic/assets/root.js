

// Fill
g.on("click", ".fill", function (el) {
  var show_parent = el.dataset.show;
  var show_parent_el = g.el(show_parent);
  var fill_data = window[el.dataset.fill];

  var pwds = g.els("[type='password']", show_parent_el);
  var del = g.el(".delete", show_parent_el);
  var del_link = g.el(".delete_link", del);

  for (var key in fill_data) {
    var obj = g.el("." + key, show_parent_el);
    if (g.is(obj)) {
      if (g.hc(obj, "quill")) {
        if (g.is(obj.quill)) {
          obj.quill.root.innerHTML = fill_data[key];
        }
      } else if (g.hc(obj, "jsoneditor_raw")) {
        var data_raw = fill_data[key];
        var editor = obj.JSONEditor;
        if (g.is(editor)) {
          var input = obj.JSONInput;
          obj.dataset.raw = data_raw;
          input.value = JSON.stringify(data_raw);
          editor.set(data_raw);
        }
      } else if (g.hc(obj, "tagify_raw")) {
        var obj_input = g.el("input." + key, show_parent_el);
        var vals = fill_data[key];
        if (g.is(obj_input.tagify)) {
          obj_input.tagify.settings.whitelist.length = 0; // reset current whitelist
          obj_input.tagify.loading(true).dropdown.hide.call(obj_input.tagify) // show the loader animation
          obj_input.tagify.removeAllTags();
          obj_input.tagify.addTags(vals);
          obj_input.tagify.loading(false);
        }
      } else if (g.hc(obj, "dropzone_raw")) {
        var dropzone_el = obj;
        if (g.is(dropzone_el.Dropzone)) {
          dropzone_el.Dropzone.removeAllFiles(true);
          dropzone_el.dataset.raw = JSON.stringify(fill_data[key]);
          g.run("mod.dropzoneProcessStored", dropzone_el);
        }
      } else {
        obj.value = fill_data[key];
      }
    }

    var lbl = g.el(".label_" + key, show_parent_el);
    if (g.is(lbl)) {
      lbl.innerHTML = fill_data[key];
    }
  }

  for (var p = 0; p < pwds.length; p++) {
    var obj = pwds[p];
    obj.value = "";
  }

  del_link.href = "";
  g.rc(del, "dn");
});

// Submit button
g.on("click", ".submit", function (el) {
  var form = el.closest("form");
  form.submit();
});

// Reset button
g.on("click", ".reset", function (el) {
  var show_parent_sel = el.dataset.form;
  show_parent = g.el(show_parent_sel);
  show_parent.reset();
  var jse = g.els(".jsoneditor_raw", show_parent);
  var rte = g.els(".quill", show_parent);
  var tel = g.els("input.tagify_raw", show_parent);
  var hiddens = g.els("[type='hidden']", show_parent);
  var pwds = g.els("[type='password']", show_parent);
  var dropzones = g.els(".dropzone_raw", show_parent);

  var del = g.el(".delete", show_parent);

  for (var j = 0; j < jse.length; j++) {
    var obj = jse[j];
    var editor = obj.JSONEditor;
    if (g.is(editor)) {
      editor.set({});
    }
  }
  for (var q = 0; q < rte.length; q++) {
    var obj = rte[q];
    if (g.is(obj.quill)) {
      obj.quill.root.innerHTML = "";
    }
  }
  for (var t = 0; t < tel.length; t++) {
    var obj_input = tel[t];
    if (g.is(obj_input.tagify)) {
      obj_input.tagify.removeAllTags();
    }
  }
  for (var h = 0; h < hiddens.length; h++) {
    var obj = hiddens[h];
    obj.value = "";
  }
  for (var p = 0; p < pwds.length; p++) {
    var obj = pwds[p];
    obj.value = "";
  }
  for (var d = 0; d < dropzones.length; d++) {
    var obj = dropzones[d];
    if (g.is(obj.Dropzone)) {
      obj.Dropzone.removeAllFiles(true);
    }
  }
  var row = g.el(".list_row.active");
  if (g.is(row)) { g.rc(row, "active"); }
  g.ac(del, "dn");
});

// Setting Form Action button
g.on("click", ".form_action", function (el) {
  var form_sel = el.dataset.form;
  var form = g.el(form_sel);
  var action = el.dataset.action;
  form.action = action;
});

// Setting Form Action button
g.on("click", ".activate", function (el) {
  var row_sel = el.dataset.activate;
  var activate = el.closest(row_sel);
  if (g.is(activate)) {
    var parent = activate.closest(".grid");
    var siblings = g.els(row_sel, parent);
    for (var i = 0; i < siblings.length; i++) {
      var row = siblings[i];
      g.rc(row, "active");
    }
    g.ac(activate, "active");
  }
});

// Setting Hide deactivate button
g.on("click", ".deact", function (el) {
  var parent = el.closest(".dr_window");
  var id_input = g.el(".id", parent);
  var id = id_input.value;
  var row = g.el(".list_row_" + id);
  if (g.is(row)) { g.rc(row, "active"); }
});
