// for use with use with IE :(
var id,tags = document.getElementsByTagName('SCRIPT');
ar = (tags[tags.length-1]).src.split('?');
id = ar[1] ? ar[1] : '';
html.callback(id);