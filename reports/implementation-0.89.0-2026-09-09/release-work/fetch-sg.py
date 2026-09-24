import urllib.request, pathlib, zipfile, io, hashlib, json
root=pathlib.Path(__file__).parent/'sg-reference'
root.mkdir(exist_ok=True)
url='https://downloads.wordpress.org/plugin/sg-cachepress.7.8.1.zip'
data=urllib.request.urlopen(url,timeout=45).read()
z=zipfile.ZipFile(io.BytesIO(data))
names=['core/Options/Options.php','core/Rest/Rest.php','core/Rest/Rest_Helper_Options.php','sg-cachepress.php']
for name in names:
    b=z.read('sg-cachepress/'+name)
    (root/name.replace('/','-')).write_bytes(b)
    print(json.dumps({'file':name,'sha256':hashlib.sha256(b).hexdigest()}))
