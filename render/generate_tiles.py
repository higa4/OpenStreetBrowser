#!/usr/bin/python
from math import pi,cos,sin,log,exp,atan
from subprocess import call
import sys, os

DEG_TO_RAD = pi/180
RAD_TO_DEG = 180/pi

def minmax (a,b,c):
    a = max(a,b)
    a = min(a,c)
    return a

class GoogleProjection:
    def __init__(self,levels=18):
        self.Bc = []
        self.Cc = []
        self.zc = []
        self.Ac = []
        c = 256
        for d in range(0,levels):
            e = c/2;
            self.Bc.append(c/360.0)
            self.Cc.append(c/(2 * pi))
            self.zc.append((e,e))
            self.Ac.append(c)
            c *= 2
                
    def fromLLtoPixel(self,ll,zoom):
         d = self.zc[zoom]
         e = round(d[0] + ll[0] * self.Bc[zoom])
         f = minmax(sin(DEG_TO_RAD * ll[1]),-0.9999,0.9999)
         g = round(d[1] + 0.5*log((1+f)/(1-f))*-self.Cc[zoom])
         return (e,g)
     
    def fromPixelToLL(self,px,zoom):
         e = self.zc[zoom]
         f = (px[0] - e[0])/self.Bc[zoom]
         g = (px[1] - e[1])/-self.Cc[zoom]
         h = RAD_TO_DEG * ( 2 * atan(exp(g)) - 0.5 * pi)
         return (f,h)

from mapnik import *

def render_tiles(bbox, mapfile, tile_dir, minZoom=1,maxZoom=18, name="unknown", layer="unknown"):
    print "render_tiles(",bbox, mapfile, tile_dir, minZoom, maxZoom, name, layer,")"

    if not os.path.isdir(tile_dir):
         os.mkdir(tile_dir)

    gprj = GoogleProjection(maxZoom+1) 
    m = Map(2 * 256,2 * 256)
    load_map(m,mapfile)
    prj = Projection("+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +no_defs +over")
    
    ll0 = (bbox[0],bbox[3])
    ll1 = (bbox[2],bbox[1])
    
    for z in range(minZoom,maxZoom + 1):
        px0 = gprj.fromLLtoPixel(ll0,z)
        px1 = gprj.fromLLtoPixel(ll1,z)

        # check if we have directories in place
        zoom = "%s" % z
        if not os.path.isdir(tile_dir + zoom):
            os.mkdir(tile_dir + zoom)
        for x in range(int(px0[0]/256.0),int(px1[0]/256.0)+1):
            # check if we have directories in place
            str_x = "%s" % x
            if not os.path.isdir(tile_dir + zoom + '/' + str_x):
                os.mkdir(tile_dir + zoom + '/' + str_x)
            for y in range(int(px0[1]/256.0),int(px1[1]/256.0)+1):
                p0 = gprj.fromPixelToLL((x * 256.0, (y+1) * 256.0),z)
                p1 = gprj.fromPixelToLL(((x+1) * 256.0, y * 256.0),z)

                # render a new tile and store it on filesystem
                c0 = prj.forward(Coord(p0[0],p0[1]))
                c1 = prj.forward(Coord(p1[0],p1[1]))
            
                bbox = Envelope(c0.x,c0.y,c1.x,c1.y)
                bbox.width(bbox.width() * 2)
                bbox.height(bbox.height() * 2)
                m.zoom_to_box(bbox)
                
                str_y = "%s" % y

                tile_uri = tile_dir + zoom + '/' + str_x + '/' + str_y + '.png'

		exists= ""
                if os.path.isfile(tile_uri):
                    exists= "exists"
                else:
                    im = Image(512, 512)
                    render(m, im)
                    view = im.view(128,128,256,256) # x,y,width,height
                    view.save(tile_uri,'png')
                    command = "convert  -colors 255 %s %s" % (tile_uri,tile_uri)
                    call(command, shell=True)

                bytes=os.stat(tile_uri)[6]
		empty= ''
                if bytes == 137:
                    empty = " Empty Tile "

                print name,"|",layer,"[",minZoom,"-",maxZoom,"]: " ,z,x,y,"p:",p0,p1,exists, empty

if __name__ == "__main__":
    #home = os.environ['HOME']
    try:
        mapfile = os.environ['MAPNIK_MAP_FILE']
    except KeyError:
        mapfile = home + "/svn.openstreetmap.org/applications/rendering/mapnik/osm-local.xml"
    try:
        tile_dir = os.environ['MAPNIK_TILE_DIR']
    except KeyError:
        tile_dir = home + "/osm/tiles/"

    #-------------------------------------------------------------------------
    #
    # Change the following for different bounding boxes and zoom levels
    #
    # Start with an overview
    # World
bbox = (float(os.environ['MINLON']), float(os.environ['MINLAT']), float(os.environ['MAXLON']), float(os.environ['MAXLAT']))
render_tiles(bbox, mapfile, os.environ['MAPNIK_TILE_DIR'], int(os.environ['MINZOOM']), int(os.environ['MAXZOOM']), os.environ['PLACE'], os.environ['LAYER'])
