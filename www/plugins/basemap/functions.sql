CREATE OR REPLACE FUNCTION basemap_rotate_line(text, hstore, geometry, float, float) RETURNS text AS $$
DECLARE
  id alias for $1;
  tags alias for $2;
  way alias for $3;
  angle float;
  length alias for $5;
  ret geometry;
BEGIN
  angle:=$4;
  if angle is null then
    angle:=pi()/2;
  end if;

  ret:=ST_GeomFromText('LINESTRING(0 -'||(length)||',0 '||(length)||')');
  ret:=ST_Rotate(ret, -angle);
  ret:=ST_Translate(ret, X(way), Y(way));

  return ret;
END;
$$ LANGUAGE plpgsql immutable;

CREATE OR REPLACE FUNCTION basemap_places_get_name(text, hstore, geometry) RETURNS text AS $$
DECLARE
  id alias for $1;
  tags alias for $2;
  way alias for $3;
  name text;
  m text[];
BEGIN
  name:=tags->'name';

  -- if name looks like 'Text (Text)' and the text in brackets matches the
  -- English name, only the first Text will be displayed. This is used for
  -- many place names in Asia.
  m:=regexp_matches(name, E'^(.*[^ ])\\s*\\((.*)\\)$');
  if array_count(m)=2 then
    if m[2]=basemap_places_get_name_en(id, tags, way) then
      name:=m[1];
    end if;
  end if;

  return name;
END;
$$ LANGUAGE plpgsql immutable;

CREATE OR REPLACE FUNCTION basemap_places_get_name_en(text, hstore, geometry) RETURNS text AS $$
DECLARE
  id alias for $1;
  tags alias for $2;
  way alias for $3;
BEGIN
  return (CASE
	   WHEN (tags->'name:en')!=(tags->'name') THEN (tags->'name:en')
	  END);
END;
$$ LANGUAGE plpgsql immutable;
