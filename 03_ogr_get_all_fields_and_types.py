#!/usr/bin/python
#Simple script for getting info (projection and extent) from a shapefile

import cgi, sys, simplejson
from osgeo import ogr, osr

def main():
    print "Content-type: text/plain"
    print
    
    #check for filepath
    params = cgi.FieldStorage()
    fn = params.getvalue("filepath")
    if fn is None:
        print 'No filepath supplied.'
        sys.exit(1)
        
    #open the file
    dataSource = ogr.Open(fn, 0)
    if dataSource is None:
        print 'Could not open ' + fn
        sys.exit(1) #exit with an error code
    
    #get the layer and driver
    driver = dataSource.GetDriver().GetName()
    layer = dataSource.GetLayer()      

    daLayer = dataSource.GetLayer(0)
    layerDefinition = daLayer.GetLayerDefn()

    #print "Name  -  Type  Width  Precision"
    field_names = []
    field_types = []
    for i in range(layerDefinition.GetFieldCount()):
        fieldName =  layerDefinition.GetFieldDefn(i).GetName()
        fieldTypeCode = layerDefinition.GetFieldDefn(i).GetType()
        fieldType = layerDefinition.GetFieldDefn(i).GetFieldTypeName(fieldTypeCode)
        fieldWidth = layerDefinition.GetFieldDefn(i).GetWidth()
        GetPrecision = layerDefinition.GetFieldDefn(i).GetPrecision()
        #if fieldType in ['Integer', 'Real']:
        field_names.append(fieldName)
        field_types.append(fieldType)

        #print fieldName + " - " + fieldType+ " " + str(fieldWidth) + " " + str(GetPrecision)    
    #get the info
    extent = layer.GetExtent()
    spatialRef = layer.GetSpatialRef()
    featurecount = layer.GetFeatureCount()
    layername = layer.GetName()
    geometrytype = layer.GetLayerDefn().GetGeomType() 
    
    #transform the extent
    ul = ogr.Geometry(ogr.wkbPoint)
    lr = ogr.Geometry(ogr.wkbPoint)
    ul.AddPoint(extent[0], extent[3])
    lr.AddPoint(extent[1], extent[2])
    targetSpatialRef = osr.SpatialReference()
    targetSpatialRef.ImportFromEPSG(3857)
    coordTrans = osr.CoordinateTransformation(spatialRef, targetSpatialRef)
    ul.Transform(coordTrans)
    lr.Transform(coordTrans)
    
    #consolidate and return the info
    #info = dict(projection=spatialRef.ExportToProj4(), extent=extent, featurecount=featurecount, layername=layername, geometrytype=geometrytype, ul=ul.ExportToWkt(), lr=lr.ExportToWkt(), driver=driver, field_names=field_list)
    info = dict(field_names=field_names, field_types=field_types)
    print simplejson.dumps(info)
    
    #cleanup
    dataSource.Destroy()
    ul.Destroy()
    lr.Destroy()
	
main()