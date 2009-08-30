
addInitEvent(function(){
    var opts = $('media__opts');
    if(!opts) return;

    var l = document.createElement('label');
    l.innerHTML = '<a href="?do=imageshack" class="plg_imageshack">'+LANG['plugins']['imageshack']['name']+'</a>';
    opts.appendChild(l);

});
