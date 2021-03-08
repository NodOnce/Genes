/*!
 * genes.js v2021.02.15
 * (c) 2021 NodOnce OÜ
 * All rights reserved.
 */
(function (g) {
  // PRIVATE APP VARIABLES OBJECT
  var app = { debug: false, env: {}, void: {} };
  // PRIVATE FUNCTION DEFINITIONS OBJECT
  var fns = { evts: {} };

  // PUBLIC CORE FUNCTIONS
  // shortcuts for app attributes
  g.set = function (k, v) { modifyPrivateObject(app, 1, k, v); g.ot(k, v); }; // set an app variable attribute key to value
  g.get = function (k) { return modifyPrivateObject(app, 0, k); }; // get an app variable by attribute key
  g.dig = function (obj, k) { return modifyPrivateObject(obj, 0, k); }; // get an app variable by attribute key
  g.del = function (k) { modifyPrivateObject(app, 5, k); }; // delete an app attribute key
  g.ins = function (k, v, i) { }; // insert a value to app variable attribute key value array by index --- TODO
  g.rem = function (k, v, i) { modifyPrivateObject(app, 4, k, v); }; // remove a value from an app variable attribute key value array, index optional
  // shortcuts for fns functions
  g.def = function (k, c) { modifyPrivateObject(fns, 1, k, c); }; // define a function with a key
  g.ret = function (k) { return modifyPrivateObject(fns, 0, k); }; // return a function with a key
  g.run = function (k) {
    var fn = modifyPrivateObject(fns, 0, k);
    if (g.is(fn)) {
      var args = Array.prototype.slice.call(arguments);
      var al = args.length;
      if (al > 1) {
        args.shift();
      }
      else {
        args = null;
      }
      if (Array.isArray(fn)) {
        for (var i = 0; i < fn.length; i++) {
          fn[i].apply(null, args);
        }
      } else {
        if (typeof fn === 'object' && g.is(fn)) {
          for (var item in fn) {
            if (g.is(fn[item])) {
              fn[item][0].apply(null, args);
            }
          }
        } else {
          fn.apply(null, args);
        }
      }
    } else {
      g.cl("Sorry, no function is defined to: " + k);
    }
  }; // run a function with a key
  g.end = function (k) { }; // delete a function from fns cache with a key --- TODO
  g.que = function (k, c) { modifyPrivateObject(fns, 2, k, c); }; // insert function to a queue key to be triggerred --- TODO
  //g.quo = function (k, c) { }; // set a queue key to a function to be triggerred once --- TODO
  g.qud = function (k) { }; // delete queued function key --- TODO

  // PUBLIC UTILITY FUNCTIONS
  g.is = function (v) {
    return !(
      typeof v === "undefined" ||
      v === null ||
      v === "null" ||
      v === "" ||
      v === false
    );
  }; // is defined or has a value
  g.is_fnc = function (fnc) {
    if (fnc instanceof Function) {
      return true;
    }
    return false;
  };
  g.is_obj = function (item) {
    return (typeof item === "object" && !Array.isArray(item) && item !== null);
  };
  g.cl = function (str_or_arr_or_obj) {
    if (app.debug && g.is(document.body)) {
      var dd = document.getElementById("debug");
      if (!g.is(dd)) {
        dd = document.createElement("div");
        dd.id = "debug";
        dd.classList.add("dn");
        document.body.appendChild(dd);
      }
      var line = (g.is_obj(str_or_arr_or_obj)) ? JSON.stringify(str_or_arr_or_obj) : str_or_arr_or_obj;
      dd.innerHTML = "<span>" + g.now() + " | " + line + "</span>" + dd.innerHTML;
    }
    if (app.debug) {
      console.log(str_or_arr_or_obj);
    }
  }; // console.log with time
  g.now = function (ms, no_ms) {
    var d = (g.is(ms)) ? new Date(parseFloat(ms.toString())) : new Date();
    var f = "Y-m-d H:i:s.v";
    if (g.is(no_ms)) { f = "Y-m-d H:i:s"; }
    return f
      .replace(/Y/gm, d.getFullYear().toString())
      .replace(/m/gm, ('0' + (d.getMonth() + 1)).substr(-2))
      .replace(/d/gm, ('0' + (d.getDate())).substr(-2))
      .replace(/H/gm, ('0' + (d.getHours())).substr(-2))
      .replace(/i/gm, ('0' + (d.getMinutes())).substr(-2))
      .replace(/s/gm, ('0' + (d.getSeconds())).substr(-2))
      .replace(/v/gm, ('0000' + (d.getMilliseconds() % 1000)).substr(-3));
  }; // simple time function with ms, eg. 1519073776123
  g.url = function (url) {
    if (!g.is(url)) {
      url = window.location.href;
    }
    var qss = url.replace(document.baseURI, "");
    var qs_rest = "";
    var qsr = {};

    if (g.is(qss)) {
      if (qss.indexOf("/") > -1) {
        var folders = qss.split("/");
        var fl = folders.length;
        qss = (g.is(folders[fl - 1])) ? folders[fl - 1] : "";
        if (!g.is(qsr.folder)) { qsr.folder = []; }
        for (var f = 0; f < (fl - 1); f++) {
          qsr.folder.push(folders[f]);
        }
      }
      if (qss.indexOf("?") > -1) {
        var q = qss.split('?');
        for (var i = 0; i < q.length; i++) {
          if (q[i].toString().indexOf("&") > -1) {
            var qq = decodeURIComponent(q[i]).split('&');
            for (var ii = 0; ii < qq.length; ii++) {
              if (qq[ii].toString().indexOf(";") > -1) {
                var qqq = decodeURIComponent(qq[ii]).split(';');
                for (var iii = 0; iii < qqq.length; iii++) {
                  if (qqq[iii].toString().indexOf("=") > -1) {
                    var qqqq = decodeURIComponent(qqq[iii]).split('=');
                    qsr[qqqq[0]] = qqqq[1];
                  }
                  else {
                    qsr[qqq[iii]] = 1;
                  }
                }
              }
              else {
                qsr[qq[ii]] = 1;
              }
            }
          }
          else {
            if (g.is(q[i])) {
              qsr[q[i]] = 1;
            }
          }
        }
      }
    }
    if (Object.keys(qsr).length === 0) { qsr = { "index": 1 }; }
    g.cl(qsr);
    g.set("meta.url", qsr);
  }; // get url querystrings
  g.si = function (k, c, ms) {
    app.void[k] = setInterval(c, ms);
  }; // shortcut for setInterval
  g.st = function (k, c, ms) {
    app.void[k] = setTimeout(c, ms);
  }; // shortcut for setTimeout
  g.ci = function (k) {
    clearInterval(app.void[k]);
  }; // shortcut for clearInterval
  g.ct = function (k) {
    clearTimeout(app.void[k]);
  }; // shortcut for clearTimeout
  g.on = function (name, selector, callback) {
    var enc_selector = g.enc(selector);
    g.def("evts." + name + "." + enc_selector, callback);
  }; // do something on some event
  g.el = function (selector, parent) {
    if (!g.is(parent)) { parent = document; }
    return parent.querySelector(selector);
  }; // get element : single item, first selected  
  g.els = function (selector, parent) {
    if (!g.is(parent)) { parent = document; }
    return parent.querySelectorAll(selector);
  }; // get elements : array
  g.hc = function (target, className) {
    return hasClass(target, className);
  }; // return if target has specified class
  g.ac = function (target, className) {
    target.classList.add(className);
  }; // add class to target
  g.rc = function (target, className) {
    target.classList.remove(className);
  }; // remove class from target
  g.tc = function (target, className) {
    if (g.hc(target, className)) {
      g.rc(target, className);
    }
    else {
      g.ac(target, className);
    }
  }; // remove class from target
  g.enc = function (str) {
    var salt = "qwertyuopasdfghjklizxcvbnm";
    var textToChars = function (str) {
      return str.split('').map(c => c.charCodeAt(0));
    };
    var byteHex = function (n) {
      return ("0" + Number(n).toString(16)).substr(-2);
    };
    var applySaltToChar = function (code) {
      return textToChars(salt).reduce((a, b) => (a ^ b), code);
    };

    return str.split('')
      .map(textToChars)
      .map(applySaltToChar)
      .map(byteHex)
      .join('');
  }; // cipher in
  g.dec = function (estr) {
    var salt = "qwertyuopasdfghjklizxcvbnm";
    var textToChars = function (estr) {
      return estr.split('').map(c => c.charCodeAt(0));
    };
    var saltChars = textToChars(salt);
    var applySaltToChar = function (code) {
      return textToChars(salt).reduce((a, b) => (a ^ b), code);
    };
    return estr.match(/.{1,2}/g)
      .map(hex => parseInt(hex, 16))
      .map(applySaltToChar)
      .map(charCode => String.fromCharCode(charCode))
      .join('');
  }; // cipher out

  // PUBLIC OBSERVABLE OBJECT FUNCTIONS
  g.oo = function (key, callback) {
    var cp_key = g.enc(key);
    g.que("oo." + cp_key, callback);
  }; // set a key to watch for change via g.set or similar to trigger callback
  g.ot = function (key, value) {
    var cp_key = g.enc(key);
    g.run("oo." + cp_key);
  }; // observable object trigger

  // PUBLIC AJAX FUNCTIONS
  g.xg = function (url, success, error) {
    gAjaxGet(url, success, error);
  }; // ajax get function
  g.xp = function (url, data, success, error) {
    gAjaxPost(url, data, success, error);
  }; // ajax post function
  g.xpj = function (url, data, success, error) {
    gAjaxPost(url, data, success, error, true);
  }; // ajax post as json function
  g.xsf = function (el) {
    var obj = {};
    var elements = g.els("input, select, textarea", el);
    for (var i = 0; i < elements.length; ++i) {
      var element = elements[i];
      var value = "";
      var name = element.name;

      if (element.type === 'radio' || element.type === 'checkbox') {
        if (element.checked) {
          value = element.value;
        }
      } else {
        value = element.value;
      }

      if (name) {
        obj[name] = value;
      }
    }
    return JSON.stringify(obj);
  }; // serialize form inputs inside an element to post with ajax

  g.cos = function (cName, key, value, days) {
    var cookie = g.coog(cName);
    if (g.is(cookie)) {
      cValue = cookie;
    }
    else {
      cValue = {};
    }
    cValue[key] = value;
    cValue = JSON.stringify(cValue);
    var daysToExpire = 30;
    var date = new Date();
    date.setTime(date.getTime() + (daysToExpire * 24 * 60 * 60 * 1000));
    document.cookie = cName + "=" + cValue + "; expires=" + date.toGMTString();
  }; // cookie set
  g.cog = function (cName, key) {
    var name = cName + "=";
    var allCookieArray = document.cookie.split(';');
    for (var i = 0; i < allCookieArray.length; i++) {
      var temp = allCookieArray[i].trim();
      if (temp.indexOf(name) == 0) {
        var valueSet = temp.substring(name.length, temp.length);
        if (!g.is(key)) {
          return JSON.parse(valueSet);
        }
        else {
          var cValue = JSON.parse(valueSet);
          return cValue[key];
        }
      }
    }
    return "";
  }; // cookie get
  g.cod = function (cName) {
    document.cookie = cName + "=;expires = Thu, 01 Jan 1970 00:00:00 GMT"
  }; // cookie del

  // TEMPLATING & HTML RENDER FUNCTIONS
  // CLIENT SIDE RENDER & ROUTING 
  // IS UNDER OPTIMIZATION AND FORMATTING
  // IT WILL BE PUBLISHED WITH CLONE SAMPLES
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...
  // ...

  // PRIVATE UTILITY FUNCTIONS
  function modifyPrivateObject(o, m, k, v, i) {
    if (typeof k === "string") {
      if (k.indexOf(".") > 0) {
        return modifyPrivateObject(o, m, k.split("."), v, i);
      } else {
        return modifyPrivateObject(o, m, [k], v, i);
      }
    } else if (k.length > 1) {
      if (!o.hasOwnProperty(k[0])) {
        o[k[0]] = {};
      }
      // mode == set
      if (m === 1 || m === 2 || m === 3) {
        var t_oval = o[k[0]];
        if (g.is(k[1]) && t_oval !== "" && typeof t_oval === "string") {
          // if it has a string value, and we are going to add a sub branch, 
          // the only way to clear that value.
          o[k[0]] = {};
        }
      }
      return modifyPrivateObject(o[k[0]], m, k.slice(1), v, i);
    } else if (k.length === 1) {
      if (m === 1) {
        o[k[0]] = v;
        return v;
      } else if (m === 2 || m === 3 || m === 4 || m === 5) {
        var t_oval = o[k[0]];
        if (typeof t_oval === "string") {
          o[k[0]] = [t_oval];
        } else if (g.is(t_oval) && !Array.isArray(t_oval)) {
          o[k[0]] = [t_oval];
        } else if (!Array.isArray(t_oval) && (m !== 2 && m !== 3)) {
          g.cl(t_oval + " is not an array");
          return false;
        } else if (!Array.isArray(t_oval) && (m === 2 || m === 3)) {
          o[k[0]] = [];
        }

        if (m === 2 && g.is(o[k[0]])) {
          o[k[0]].push(v);
        } else if (m === 3) {
          o[k[0]].unshift(v);
        } else if (m === 4) {
          o[k[0]] = arrayRemove(o[k[0]], v);
        } else if (m === 5) {
          delete o[k[0]];
        }
      } else {
        if (Array.isArray(k)) {
          return o[k[0]];
        } else {
          return o[k];
        }
      }
    }
  }
  function arrayRemove(arr, value) {
    return arr.filter(function (ele) {
      return ele != value;
    });
  }
  function setEnvironment() {
    g.url();
    g.cl("Genes environment set.");
  }
  function listenEvents(event_type) {
    if (!g.is(fns.evts[event_type])) {
      fns.evts[event_type] = {};
    }
    document.addEventListener(event_type, function (e) {
      var event_branch = fns.evts[event_type];
      for (var enc_selector in event_branch) {
        var selector = g.dec(enc_selector);
        var func = event_branch[enc_selector];
        var el = e.target.closest(selector);
        // g.cl(el);
        if (el) {
          event.preventDefault();
          func(el);
        }
      }
    });
  }
  function hasClass(target, className) {
    return new RegExp('(\\s|^)' + className + '(\\s|$)').test(target.className);
  } // Target element has the specified class?
  function gCheckMobile() {
    var isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    if (isMobile) {
      g.ac(g.el("html"), "mobile");
    } else {
      g.ac(g.el("html"), "desktop");
    }
    return isMobile;
  } // Check if Mobile
  function gDetectUserIsHuman() {
    var checks = {
      KEYUP: 'keyup',
      MOUSE: 'mousemove',
      SWIPE: 'swipe',
      SWIPE_TOUCHSTART: 'touchstart',
      SWIPE_TOUCHMOVE: 'touchmove',
      SWIPE_TOUCHEND: 'touchend',
      SCROLL: 'scroll',
      GESTURE: 'gesture',
      GYROSCOPE: 'gyroscope',
      DEVICE_MOTION: 'devicemotion',
      DEVICE_ORIENTATION: 'deviceorientation',
      DEVICE_ORIENTATION_MOZ: 'MozOrientation'
    };
    var funcs = {};

    // Add all event listeners.
    var addAllEventListeners = function () {
      for (var key in checks) {
        var action = checks[key];
        funcs[key] = function (e) {
          g.set("user.is_human", true);
          g.ac(g.el("html"), "human");
          g.cl("Yay, " + e.type + ", user is human!");
          g.run("on_human_detected");
          removeAllEventListeners();
        };

        document.addEventListener(action, funcs[key]);
      }
    };

    // If human is decided remove all event listeners.
    var removeAllEventListeners = function () {
      for (var key in checks) {
        var action = checks[key];
        document.removeEventListener(action, funcs[key]);
      }
    };

    var is_human = g.get("user.is_human");
    if (is_human) {
      g.ac(g.el("html"), "human");
      g.cl("Yay, user is human!");
    } else {
      g.cl("Sorry, user is not human?!");
      addAllEventListeners();
    }
  } // Check if Human
  function gAjaxGet(url, success, error) {
    var xhr = window.XMLHttpRequest ?
      new XMLHttpRequest() :
      new ActiveXObject("Microsoft.XMLHTTP");
    xhr.open("GET", url);
    xhr.onreadystatechange = function () {
      if (xhr.readyState > 3 && xhr.status === 200) {
        if (typeof success === "function") {
          success(xhr.responseText);
        }
      } else if (xhr.status !== 200) {
        if (typeof error === "function") {
          error(xhr);
        }
      } else {
        /*
        console.log(
          "Loading: " + url + " :: " + xhr.readyState + " :: " + xhr.status
        );
        */
      }
    };
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.send();
    return xhr;
  } // Ajax Get
  function gAjaxPost(url, data, success, error, isJson) {
    var params =
      typeof data == "string" ?
        data :
        Object.keys(data)
          .map(function (k) {
            return encodeURIComponent(k) + "=" + encodeURIComponent(data[k]);
          })
          .join("&");

    var xhr = window.XMLHttpRequest ?
      new XMLHttpRequest() :
      new ActiveXObject("Microsoft.XMLHTTP");
    xhr.open("POST", url);
    xhr.onreadystatechange = function () {
      if (xhr.readyState > 3) {
        if (xhr.status === 200) {
          // console.log("Post success.");
          if (typeof success === "function") {
            success(xhr.responseText);
          }
        } else if (xhr.status !== 200) {
          if (typeof error === "function") {
            error(xhr);
          }
        } else {
          /*
          console.log(
            "Loading: " + url + " :: " + xhr.readyState + " :: " + xhr.status
          );
          */
        }
      }
    };
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    if (isJson) {
      xhr.setRequestHeader("Content-Type", "application/json");
    } else {
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    }
    xhr.send(params);
    return xhr;
  } // Ajax Post

  // Timezone Selector
  g.def("mod.timeZones", function () {
    var el = g.el(".tzs");
    if (g.is(el)) {
      var selected = el.dataset.select;
      var date = new Date();
      var gmt_hour = date.getUTCHours();
      var gmt_minute = date.getUTCMinutes();
      if (gmt_minute < 10) {
        gmt_minute = "0" + gmt_minute;
      }

      for (var i = -12; i < 13; i++) {
        var vselected = false;
        if (selected == i) {
          vselected = true;
        }

        var this_hour = date.setUTCHours(gmt_hour + i);
        var this_hour = date.getUTCHours();
        if (this_hour < 10) {
          this_hour = "0" + this_hour;
        }
        var vi = i;
        if (i > 0) {
          vi = "+" + i;
        }
        el.options.add(new Option(this_hour + ':' + gmt_minute + ' GMT (' + vi + ':00)', i, vselected, vselected))
      }
    }
  });
  g.def("mod.resizeTrigger", function () {
    app.env.width = window.innerWidth;
    app.env.height = window.innerHeight;
    g.ct("rt");
    g.st("rt", function () {
      window.scrollTo(0, 1);
      //var html = document.getElementsByTagName("html")[0];
      //html.style.width = app.env.width + "px";
      //html.style.height = app.env.height + "px";
      g.cl("genes.resized | " + app.env.width + "x" + app.env.height);
      g.run("on_resize");
    }, 50);
  });
  g.def("mod.preselectValues", function () {
    var sels = g.els(".preselect");
    for (var i = 0; i < sels.length; i++) {
      var sel = sels[i];
      var selt = sel.dataset.select;
      sel.value = selt;
    }
  });
  g.def("mod.drWindow", function () {
    var decidePosition = function (el) {
      var bodyRect = document.body.getBoundingClientRect(),
        elemRect = el.getBoundingClientRect();
      divLeft = elemRect.left;
      divTop = elemRect.top + bodyRect.top;
      el.style.left = divLeft + 'px';
      el.style.top = divTop + 'px';
    };
    var justMove = function (el, xpos, ypos) {
      el.style.left = xpos + 'px';
      el.style.top = ypos + 'px';
    };

    var startMoving = function (el, e) {
      if (!g.hc(el, "activate")) { g.ac(el, "activate"); decidePosition(el); }
      var zztops = g.els(".zztop");
      for (var i = 0; i < zztops.length; i++) {
        var sel = zztops[i];
        g.rc(sel, "zztop");
      }
      // g.cl("Started moving...");
      g.ac(el, "dragging");
      g.ac(el, "zztop");

      var posX = e.clientX,
        posY = e.clientY;

      var bdrc = el.getBoundingClientRect(),
        divTop = parseInt(el.style.top),
        divLeft = parseInt(el.style.left),
        eW = bdrc.width,
        eH = bdrc.height;

      var diffX = posX - divLeft,
        diffY = posY - divTop;

      document.onmousemove = function (e) {
        var posX = e.clientX,
          posY = e.clientY;

        var aX = posX - diffX,
          aY = posY - diffY;

        if (aX < 0) aX = 0;
        if (aY < 0) aY = 0;

        // g.cl({ "posX": posX, "posY": posY, "divTop": divTop, "divLeft": divLeft, "eW": eW, "eH": eH });

        justMove(el, aX, aY);
      };
      document.onmouseup = function (e) {
        e.preventDefault();
        stopMoving(el, e);
      };
      document.ontouchend = function (e) {
        e.preventDefault();
        stopMoving(el, e);
      };
    };
    var stopMoving = function (el, e) {
      g.rc(el, "dragging");
      // g.cl("Stopped moving...");
      document.onmousemove = function () { };
    };

    document.addEventListener("mousedown", function (e) {
      var trigger = e.target.closest(".dr_window_title");
      if (trigger) {
        e.preventDefault();
        var el = trigger.closest(".dr_window");
        startMoving(el, e);
      }
    });
    document.addEventListener("touchstart", function (e) {
      var trigger = e.target.closest(".dr_window_title");
      if (trigger) {
        e.preventDefault();
        var el = trigger.closest(".dr_window");
        startMoving(el, e);
      }
    });
  });

  g.que("on_load.basic_mods", function () {
    g.run("mod.timeZones");
    g.run("mod.preselectValues");
    g.run("mod.drWindow");
    document.addEventListener('keyup', function (e) {
      if (e.code === "IntlBackslash") {
        document.getElementById("debug").classList.toggle("dn");
      }
    });
    gCheckMobile();
    gDetectUserIsHuman();
  });

  g.que("render.core", function () {
    setEnvironment();
    g.cl("Genes rendered.");
  });

  g.que("init.register_events", function () {
    listenEvents("click");
    listenEvents("submit");
    //listenEvents("mouseout");
    //listenEvents("keyup");

    g.on("click", ".remove", function (el) {
      var target = el.dataset.remove;
      var remove = null;
      if (!g.is(target)) {
        target = ".removable";
        remove = el.closest(target);
      }
      else {
        remove = g.el(target);
      }

      remove.parentNode.removeChild(remove);
    });
    g.on("click", ".hide", function (el) {
      var target = el.dataset.hide;
      var hide = null;
      if (!g.is(target)) {
        target = ".hidable";
        hide = el.closest(target);
      }
      else {
        hide = g.el(target);
      }

      g.ac(hide, "dn");
    });
    g.on("click", ".show", function (el) {
      var target = el.dataset.show;
      var show = g.el(target);
      g.rc(show, "dn");
    });
    g.on("click", ".toggle", function (el) {
      var target = el.dataset.toggle;
      var toggle = g.el(target);
      g.tc(toggle, "dn");
    });
    g.on("click", ".toggle-css", function (el) {
      var target = el.dataset.toggle;
      var css = el.dataset.css;
      var toggle = g.el(target);
      g.tc(toggle, css);
    });
    g.on("click", ".swink", function (el) {
      var swapper = el.closest(".swapper");
      var target = el.dataset.swap;
      var swap = g.el(target, swapper);
      var siblinks = g.els(".swink", swapper);
      for (var i = 0; i < siblinks.length; i++) {
        var sel = siblinks[i];
        g.rc(sel, "swactive");
      }
      var siblings = g.els(".swonts", swapper);
      for (var i = 0; i < siblings.length; i++) {
        var sel = siblings[i];
        g.rc(sel, "swactive");
      }
      g.ac(swap, "swactive");
      g.ac(el, "swactive");
    });
    g.on("click", ".dd_toggle", function (el) {
      var parent = el.closest(".dd_cont");
      var toggle = g.el(".dd_menu", parent);
      g.tc(toggle, "dn");
    });
    g.on("click", ".pctv", function (el) {
      var target = el.dataset.pctv;
      var pinp = g.el(target);
      if (g.hc(el, "active")) {
        pinp.type = "password";
      }
      else {
        pinp.type = "text";
      }
      g.tc(el, "active");
    });

    g.cl("Genes events registered: click.");
  });
  g.que("init.msg", function () { g.cl("Genes init.") });
  g.run("init");

  window.addEventListener("resize", g.ret("mod.resizeTrigger"), false);
  window.addEventListener('load', function () { g.run("on_load"); });
  g.run("mod.resizeTrigger");
})((window.g = {}));
