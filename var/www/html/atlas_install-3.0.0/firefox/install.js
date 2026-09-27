const author            = "Alessandro De Salvo";
const displayName       = "ATLAS software installer";
const name              = "atlassw";
const version           = "1.0";
var contentFlag         = CONTENT | PROFILE_CHROME;
var folder              = getFolder("Profile", "chrome");
var localeFlag          = LOCALE | PROFILE_CHROME;
var skinFlag            = SKIN | PROFILE_CHROME;
var jarName             = name + ".jar";
var existsInApplication = File.exists(getFolder(getFolder("chrome"), jarName));
var existsInProfile     = File.exists(getFolder(folder, jarName));
const SUCCESS_MESSAGE   = " installed successfully";

initInstall(displayName, name, version);
setPackageFolder(folder);
var error1 = addFile(author, version, 'chrome/' + jarName, folder, null);
if (error1 != SUCCESS) alert("Jar installation error: "+error);
var error2 = addFile("sw-mgr");
if (error2 != SUCCESS) alert("sw-mgr installation error: "+error);
var error3 = addFile("atlassw-exec");
if (error3 != SUCCESS) alert("atlassw-exec installation error: "+error);

// If adding the JAR file succeeded

if(error1 == SUCCESS && error2 == SUCCESS && error3 == SUCCESS) {
    folder = getFolder(folder, jarName);

    registerChrome(contentFlag, folder, "content/atlassw/");
    registerChrome(skinFlag, folder, "skin/classic/atlassw/");

    error = performInstall();

    if(error == SUCCESS || error == 999) {
        alert(displayName+" "+version+" installed successfully.\n");
    } else {
        alert("Cannot install the plugin. Error code: " + error);
        cancelInstall(error);
    }

} else {
    alert("Cannot install the plugin: " + error );
    cancelInstall(error);
}
