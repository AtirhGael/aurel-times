#!/usr/bin/env python
# Extract product data from scraped RepTime/watchespro static mirror -> seed_data.json
import re, json, os, sys, html as htmllib

ROOT = r"C:\xampp\htdocs\watches"
PROD_DIR = os.path.join(ROOT, "products")
COMM_DIR = os.path.join(ROOT, "comments")
OUT = os.path.join(ROOT, "data", "seed_data.json")

LDJSON_RE = re.compile(r'<script type="application/ld\+json">(.*?)</script>', re.S)
META_DESC_RE = re.compile(r'<meta name="description" content="([^"]*)"', re.I)
OG_IMG_RE = re.compile(r'<meta property="og:image" content="([^"]*)"', re.I)

# Known watch brands (longest-match first) to normalize brand from product name
BRANDS = [
    "Audemars Piguet","Patek Philippe","Vacheron Constantin","Jaeger-LeCoultre",
    "A. Lange & Sohne","Richard Mille","Franck Muller","Ulysse Nardin",
    "Rolex","Omega","Cartier","Hublot","Breitling","Panerai","Tag Heuer","TAG Heuer",
    "IWC","Tudor","Longines","Tissot","Zenith","Chopard","Bell & Ross","Montblanc",
    "Girard-Perregaux","Blancpain","Breguet","Piaget","Glashutte","Hamilton","Seiko",
    "Grand Seiko","Rado","Oris","Maurice Lacroix","Corum","Graham","U-Boat","Bvlgari",
    "Chanel","Hermes","Roger Dubuis","Parmigiani",
]

def norm_img(u):
    if not u: return None
    u = u.strip()
    u = htmllib.unescape(u)
    if u.startswith("http://") or u.startswith("https://"):
        return u
    # strip leading ./ ../ repeatedly
    u2 = re.sub(r'^(\.\.?/)+', '', u)
    if u2.startswith("//"):
        return "https:" + u2
    # host-like start
    if re.match(r'^[a-z0-9.-]+\.(com|net|to|cc|io|cdn)[/.]', u2, re.I) or u2.startswith("cdn."):
        return "https://" + u2
    return "https://" + u2 if "." in u2.split("/")[0] else None

def brand_from_name(name):
    low = name.lower()
    for b in BRANDS:
        if low.startswith(b.lower()):
            return "TAG Heuer" if b.lower()=="tag heuer" else b
    # fallback: first word
    return name.split()[0] if name else "Other"

def load_ldjson_blocks(txt):
    out=[]
    for m in LDJSON_RE.findall(txt):
        s=m.strip()
        try:
            out.append(json.loads(s))
        except Exception:
            # sometimes multiple JSON concatenated or trailing commas; try lenient
            try:
                s2=re.sub(r',\s*([}\]])', r'\1', s)
                out.append(json.loads(s2))
            except Exception:
                pass
    return out

def find_product_block(blocks):
    for b in blocks:
        if isinstance(b, dict) and b.get("@type")=="Product":
            return b
    return None

def parse_product(path, handle):
    txt = open(path, encoding="utf-8", errors="ignore").read()
    blocks = load_ldjson_blocks(txt)
    prod = find_product_block(blocks)
    if not prod:
        return None
    name = (prod.get("name") or "").strip()
    if not name:
        return None
    imgs=[]
    im = prod.get("image")
    if isinstance(im, str): im=[im]
    if isinstance(im, list):
        for u in im:
            n=norm_img(u if isinstance(u,str) else "")
            if n and n not in imgs: imgs.append(n)
    if not imgs:
        m=OG_IMG_RE.search(txt)
        if m:
            n=norm_img(m.group(1))
            if n: imgs.append(n)
    # variants from offers
    variants=[]
    offers = prod.get("offers")
    if isinstance(offers, dict): offers=[offers]
    if isinstance(offers, list):
        for o in offers:
            if not isinstance(o,dict): continue
            try: price=float(str(o.get("price","0")).replace(",",""))
            except: price=0.0
            grade=(o.get("name") or "Standard").strip() or "Standard"
            sku=(o.get("sku") or o.get("mpn") or "").strip()
            avail = o.get("availability","")
            instock = 1 if "InStock" in str(avail) else 1  # default in stock
            variants.append({"grade":grade,"price":round(price,2),"sku":sku,"in_stock":instock})
    # dedupe variants by grade keeping first
    seen=set(); vv=[]
    for v in variants:
        if v["grade"] in seen: continue
        seen.add(v["grade"]); vv.append(v)
    variants=vv
    # description
    desc=""
    m=META_DESC_RE.search(txt)
    if m: desc=htmllib.unescape(m.group(1)).strip()
    mpn=(prod.get("mpn") or "").strip()
    brand=brand_from_name(name)
    base_price = min([v["price"] for v in variants], default=0.0)
    return {
        "handle":handle,"name":name,"brand":brand,"mpn":mpn,
        "description":desc,"images":imgs,"variants":variants,
        "base_price":base_price,
    }

def parse_reviews(handle):
    path=os.path.join(COMM_DIR, handle+".html")
    if not os.path.isfile(path): return []
    txt=open(path,encoding="utf-8",errors="ignore").read()
    revs=[]
    # ld+json Review blocks
    for b in load_ldjson_blocks(txt):
        items=[]
        if isinstance(b,dict) and b.get("@type")=="Product":
            r=b.get("review")
            if isinstance(r,list): items=r
            elif isinstance(r,dict): items=[r]
        for r in items:
            if not isinstance(r,dict): continue
            author=r.get("author")
            if isinstance(author,dict): author=author.get("name","")
            rating=None
            rr=r.get("reviewRating")
            if isinstance(rr,dict):
                try: rating=int(float(rr.get("ratingValue",0)))
                except: rating=None
            body=(r.get("reviewBody") or r.get("description") or "").strip()
            revs.append({"author":(author or "Anonymous").strip()[:80],
                         "rating":rating or 5,"body":htmllib.unescape(body)[:1000]})
    return revs

def main():
    files=[f for f in os.listdir(PROD_DIR) if f.endswith(".html") and not f.startswith("{{")]
    products=[]; skipped=[]
    brands=set(); total_reviews=0
    n=len(files)
    for idx,f in enumerate(sorted(files)):
        if idx % 100 == 0:
            print(f"  ...{idx}/{n}", flush=True)
        handle=f[:-5]
        try:
            p=parse_product(os.path.join(PROD_DIR,f), handle)
        except Exception as e:
            skipped.append((handle,str(e))); continue
        if not p or not p["variants"]:
            skipped.append((handle,"no product/variants")); continue
        # Reviews are rendered client-side (no structured data in static HTML),
        # so we skip the 1142 comment files and synthesize sample reviews at seed time.
        p["reviews"]=[]
        brands.add(p["brand"])
        products.append(p)
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    json.dump({"products":products,"brands":sorted(brands)},
              open(OUT,"w",encoding="utf-8"), ensure_ascii=False)
    print(f"parsed products: {len(products)}")
    print(f"skipped: {len(skipped)}")
    print(f"brands ({len(brands)}): {', '.join(sorted(brands))}")
    print(f"total reviews: {total_reviews}")
    print(f"out: {OUT} ({os.path.getsize(OUT)} bytes)")
    if skipped[:5]:
        print("sample skips:", skipped[:5])
    # brand histogram
    from collections import Counter
    c=Counter(p["brand"] for p in products)
    print("top brands:", c.most_common(12))
    # variant grades seen
    grades=Counter(v["grade"] for p in products for v in p["variants"])
    print("grades:", grades.most_common())

if __name__=="__main__":
    main()
