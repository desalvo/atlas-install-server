/* -*- Mode: Java; tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is the ATLASSW protocol handler 1.0.
 *
 * The Initial Developer of the Original Code is
 * Alessandro De Salvo <Alessandro.DeSalvo@roma1.infn.it>
 * Portions created by the Initial Developer are Copyright (C) 2003 - 2008
 * the Initial Developer. All Rights Reserved.
 *
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK ***** */

/***** Defines *****/

// components defined in this file
const ATLASSWPROT_HANDLER_CONTRACTID =
    "@mozilla.org/network/protocol;1?name=atlassw";
const ATLASSWPROT_HANDLER_CID =
    Components.ID("{53b7b335-0ad1-4eb5-90ea-47c0893fda09}");

// components used in this file
const NS_IOSERVICE_CID = "{9ac9e770-18bc-11d3-9337-00104ba0fd40}";
const NS_PREFSERVICE_CONTRACTID = "@mozilla.org/preferences-service;1";
const URI_CONTRACTID = "@mozilla.org/network/simple-uri;1";
const NS_WINDOWWATCHER_CONTRACTID = "@mozilla.org/embedcomp/window-watcher;1";
const INPUTSTREAMCHANNEL_CONTRACTID = "@mozilla.org/network/input-stream-channel;1";
const CATEGORY_MANAGER_CONTRACTID = "@mozilla.org/categorymanager;1";
const HTTP_HANDLER_CONTRACTID = "@mozilla.org/network/protocol;1?name=http";
const MEDIATOR_CONTRACTID = "@mozilla.org/appshell/window-mediator;1";
const ASS_CONTRACTID = "@mozilla.org/appshell/appShellService;1";



// interfaces used in this file
const nsIProtocolHandler    = Components.interfaces.nsIProtocolHandler;
const nsIURI                = Components.interfaces.nsIURI;
const nsIPrefService        = Components.interfaces.nsIPrefService;
const nsIWindowWatcher      = Components.interfaces.nsIWindowWatcher;
const nsIChannel            = Components.interfaces.nsIChannel;
const nsIContentPolicy      = Components.interfaces.nsIContentPolicy;
const nsIWindowMediator     = Components.interfaces.nsIWindowMediator;
const nsIAppShellService    = Components.interfaces.nsIAppShellService;


// some misc. constants
const PREF_BRANCH   = "extensions.atlassw.";
const WND_WIDTH = 320;
const WND_HEIGHT = 200;

// configuration (and defaults)
myWnd               = null;
cfgMinDiskSpace     = 8000000;
cfgLocation         = "/atlas";
cfgPacmanVersion    = "3.25";
cfgValidate         = true;
cfgPublish          = true;
cfgBenchmark        = true;
cfgBenchmarkSuite   = "CSC";
cfgLocalCache       = true;
cfgLocalCachePath   = "/atlas/snapshots";
cfgPretendPlatform  = "auto";


/***** Utilities *****/

function parseUri(sourceUri){
    var uriPartNames = ["source","protocol","authority","domain","port","path","directoryPath","fileName","query","anchor"];
    var uriParts = new RegExp("^(?:([^:/?#.]+):)?(?://)?(([^:/?#]*)(?::(\\d*))?)?((/(?:[^?#](?![^?#/]*\\.[^?#/.]+(?:[\\?#]|$)))*/?)?([^?#/]*))?(?:\\?([^#]*))?(?:#(.*))?").exec(sourceUri);
    var uri = {};

    for(var i = 0; i < 10; i++){
        uri[uriPartNames[i]] = (uriParts[i] ? uriParts[i] : "");
    }

    if(uri.directoryPath.length > 0){
        uri.directoryPath = uri.directoryPath.replace(/\/?$/, "/");
    }

    return uri;
}

/***** ATLASswProtocolHandler *****/

function ATLASswProtocolHandler()
{
}

// attribute defaults
ATLASswProtocolHandler.prototype.defaultPort = -1;
ATLASswProtocolHandler.prototype.protocolFlags = nsIProtocolHandler.URI_NORELATIVE;
ATLASswProtocolHandler.prototype.withinLoad = false;

ATLASswProtocolHandler.prototype.allowPort = function(aPort)
{
    return false;
}

ATLASswProtocolHandler.prototype.newURI = function(aSpec, aCharset, aBaseURI)
{
    var uri = Components.classes[URI_CONTRACTID].createInstance(nsIURI);
    uri.spec = aSpec;
    return uri;
}

ATLASswProtocolHandler.prototype.loadATLASsw = function(aURI)
{
    var myURI = "";
    var myTitle = "";
    var myWidth = WND_WIDTH;
    var myHeight = WND_HEIGHT;
    var myURIComponent = encodeURIComponent(decodeURI(aURI.spec));

    myTitle = "ATLASsw";
    // Reload from the preferences
    ATLASswModule.readPreferences(PREF_BRANCH);

    // rewrite the URI into a http URL to the atlas sw client
    //myURI = "http://";
    //myURI += cfgServer + ":" + cfgPort + "/atlassw.php?rel=" + myURIComponent;
    var parsedURI   = parseUri(aURI.spec);
    var project     = parsedURI['authority'];
    var path        = parsedURI['path'].split('/');
    var release     = path[1];
    var arch        = path[2];
    if (!arch) arch = "i686_slc4_gcc34";
    var queryParts  = parsedURI['query'].split('&');

    // create a new HTTP channel with our generated URL
    //var httphandler = Components.classes[HTTP_HANDLER_CONTRACTID].getService(nsIProtocolHandler);
    //var httpURI = httphandler.newURI(myURI, "UTF-8", null);
    //return httphandler.newChannel(httpURI);
    var promptService = Components.classes["@mozilla.org/embedcomp/prompt-service;1"]
                                  .getService(Components.interfaces.nsIPromptService);
    //spawnATLASsw(myURI);
    var MY_ID = "atlassw@roma1.infn.it";
    var em = Components.classes["@mozilla.org/extensions/manager;1"].
             getService(Components.interfaces.nsIExtensionManager);
    var file = em.getInstallLocation(MY_ID).getItemFile(MY_ID, "atlassw-exec");
    var mgrfile = em.getInstallLocation(MY_ID).getItemFile(MY_ID, "sw-mgr").path;
    var process = Components.classes["@mozilla.org/process/util;1"]
                            .createInstance(Components.interfaces.nsIProcess);
    process.init(file);
    var args = new Array();
    var logfile = cfgLocation+"/log/"+project+"-"+release+".install.log";
    var loginst = "";
    args.push(logfile, mgrfile, '--dir', cfgLocation);
    var isExternal = false;
    for (i=0; i<queryParts.length; i++) {
        queryKeyVal = queryParts[i].split('=');
        args.push("--"+queryKeyVal[0]);
        if (queryKeyVal[1]) {
            if (queryKeyVal[0] == "logical" || queryKeyVal[0] == "physical")
                args.push(cfgLocation+'/'+queryKeyVal[1]);
                if (queryKeyVal[0] == "logical") loginst = cfgLocation+'/'+queryKeyVal[1];
            else
                args.push(queryKeyVal[1]);
            if (queryKeyVal[0] == "project-type" && queryKeyVal[1] == "ext")
                isExternal = true;
        }
        if (queryKeyVal[0] == "setup-latest") args.push(cfgLocation);
    }
    if (cfgValidate && !isExternal) args.push("--validate");
    args.push("-P", project, "-o", "--no-tag", "-m", cfgPacmanVersion);
    if (arch != "noarch") args.push("-t", "_"+arch); else args.push("-t", arch);
    if (cfgPublish) args.push("--kvpost");
    if (cfgLocalCache) {
        args.push("--snapdir", cfgLocalCachePath);
    } else {
        args.push("-n");
    }
    if (cfgPretendPlatform != "auto" && cfgPretendPlatform != "") args.push("--pretend-platform", cfgPretendPlatform);
    dump('atlsw-service: '+args.join()+'\n');
    process.run(false, args, args.length);
    var msg = "Installation of "+project+" "+release+" started.\n";
    msg    += "The requested software will be available at "+loginst+".\n";
    msg    += "A full installation logfile will be available at "+logfile;
    promptService.alert(null, "ATLASsw installer", msg);
}

ATLASswProtocolHandler.prototype.shouldLoad = function(ct, cl, org, ctx, mt, ext)
{
    return nsIContentPolicy.ACCEPT;
}

ATLASswProtocolHandler.prototype.shouldProcess = function(ct, cl, org, ctx, mt, ext)
{
    return nsIContentPolicy.ACCEPT;
}

ATLASswProtocolHandler.prototype.newChannel = function(aURI)
{
    // pass URI to ATLASsw
    var chan = this.loadATLASsw(aURI);

    // return a fake empty channel so current window doesn't change
    if(chan == null) chan = Components.classes[INPUTSTREAMCHANNEL_CONTRACTID].createInstance(nsIChannel);
    return chan;
}

/***** ATLASswProtocolHandlerFactory *****/

function ATLASswProtocolHandlerFactory()
{
}

ATLASswProtocolHandlerFactory.prototype.createInstance = function(outer, iid)
{
    if(outer != null) throw Components.results.NS_ERROR_NO_AGGREGATION;

    if(!iid.equals(nsIProtocolHandler) && !iid.equals(nsIContentPolicy))
        throw Components.results.NS_ERROR_INVALID_ARG;

    return new ATLASswProtocolHandler();
}

var factory_atlassw = new ATLASswProtocolHandlerFactory();

/***** ATLASswModule *****/

var ATLASswModule = new Object();

function spawnATLASsw(uri, count)
{
    var e;

    var wmClass = Components.classes[MEDIATOR_CONTRACTID];
    var windowManager = wmClass.getService(nsIWindowMediator);

    var assClass = Components.classes[ASS_CONTRACTID];
    var ass = assClass.getService(nsIAppShellService);
    hiddenWin = ass.hiddenDOMWindow;

    // Ok, not starting currently, so check if we've got existing windows.
    var w = windowManager.getMostRecentWindow("atlassw:atlassw");

    // Claiming that a ATLASsw window is loading.
    if ("ATLASswStarting" in hiddenWin)
    {
        dump("atlsw-service: ATLASsw claiming to be starting.\n");
        if (w && ("client" in w) && ("initialized" in w.client) &&
            w.client.initialized)
        {
            dump("atlsw-service: It lied. It's finished starting.\n");
            // It's actually loaded ok.
            delete hiddenWin.ATLASswStarting;
        }
    }

    if ("ATLASswStarting" in hiddenWin)
    {
        count = count || 0;

        if ((new Date() - hiddenWin.ATLASswStarting) > 10000)
        {
            dump("atlsw-service: Continuing to be unable to talk to existing window!\n");
        }
        else
        {
            // We have a ATLASsw window, but we're still loading.
            hiddenWin.setTimeout(function wrapper(count) {
                    this.spawn(uri, count + 1);
                }, 250, count);
            return true;
        }
    }

    // We have a window.
    if (w)
    {
        dump("atlsw-service: Existing, fully loaded window. Using.\n");
        // Window is working and initialized ok. Use it.
        w.focus();
        if (uri)
            w.handleRelease(uri);
        return true;
    }

    dump("atlsw-service: No windows, starting new one.\n");
    // Ok, no available window, loading or otherwise, so start ATLASsw.
    var args = new Object();
    if (uri)
        args.url = uri;

    hiddenWin.ATLASswStarting = new Date();
    hiddenWin.openDialog("chrome://atlassw/content/atlassw.xul", "_blank",
                 "chrome,menubar,toolbar,status,resizable,dialog=no",
                 args);

    return true;
}

ATLASswModule.readPreferences = function(pref_branch)
{
    // get preferences branch
    var PrefService = Components.classes[NS_PREFSERVICE_CONTRACTID].getService(nsIPrefService);
    var myPrefs = PrefService.getBranch(null);  // Mozilla bug #107617

    // read preferences (if available)
    if(myPrefs.getPrefType(pref_branch + "mindiskfree") == myPrefs.PREF_INT)
      cfgMinDiskFree = myPrefs.getIntPref(pref_branch + "mindiskfree");
    if(myPrefs.getPrefType(pref_branch + "location") == myPrefs.PREF_STRING)
      cfgLocation = myPrefs.getCharPref(pref_branch + "location");
    if(myPrefs.getPrefType(pref_branch + "pacmanver") == myPrefs.PREF_STRING)
      cfgPacmanVersion = myPrefs.getCharPref(pref_branch + "pacmanver");
    if(myPrefs.getPrefType(pref_branch + "localcache") == myPrefs.PREF_BOOL)
      cfgLocalCache = myPrefs.getBoolPref(pref_branch + "localcache");
    if(myPrefs.getPrefType(pref_branch + "localcachepath") == myPrefs.PREF_STRING)
      cfgLocalCachePath = myPrefs.getCharPref(pref_branch + "localcachepath");
    if(myPrefs.getPrefType(pref_branch + "validate") == myPrefs.PREF_BOOL)
      cfgValidate = myPrefs.getBoolPref(pref_branch + "validate");
    if(myPrefs.getPrefType(pref_branch + "publish") == myPrefs.PREF_BOOL)
      cfgPublish = myPrefs.getBoolPref(pref_branch + "publish");
    if(myPrefs.getPrefType(pref_branch + "benchmark") == myPrefs.PREF_BOOL)
      cfgBenchmark = myPrefs.getBoolPref(pref_branch + "benchmark");
    if(myPrefs.getPrefType(pref_branch + "benchmarksuite") == myPrefs.PREF_INT)
      cfgBenchmarkSuite = myPrefs.getIntPref(pref_branch + "benchmarksuite");
    if(myPrefs.getPrefType(pref_branch + "pretendplatform") == myPrefs.PREF_STRING)
      cfgPretendPlatform = myPrefs.getCharPref(pref_branch + "pretendplatform");
}

ATLASswModule.registerSelf = function(compMgr, fileSpec, location, type)
{
    compMgr = compMgr.QueryInterface(Components.interfaces.nsIComponentRegistrar);

    // register atlassw protocol handler
    compMgr.registerFactoryLocation(ATLASSWPROT_HANDLER_CID,
                                    "ATLASsw protocol handler",
                                    ATLASSWPROT_HANDLER_CONTRACTID,
                                    fileSpec, location, type);

}

ATLASswModule.unregisterSelf = function(compMgr, fileSpec, location)
{
    compMgr = compMgr.QueryInterface(Components.interfaces.nsIComponentRegistrar);

    // unregister our components
    compMgr.unregisterFactoryLocation(ATLASSWPROT_HANDLER_CID, fileSpec);
}

ATLASswModule.getClassObject = function(compMgr, cid, iid)
{
    if(!iid.equals(Components.interfaces.nsIFactory))
        throw Components.results.NS_ERROR_NOT_IMPLEMENTED;

    // read preferences
    this.readPreferences(PREF_BRANCH);

    // return protocol handler factories
    if(cid.equals(ATLASSWPROT_HANDLER_CID)) { return factory_atlassw; }

    throw Components.results.NS_ERROR_NO_INTERFACE;
}

ATLASswModule.canUnload = function(compMgr)
{
    return true;    // our objects can be unloaded
}

/***** Entrypoint *****/

function NSGetModule(compMgr, fileSpec)
{
    return ATLASswModule;
}
