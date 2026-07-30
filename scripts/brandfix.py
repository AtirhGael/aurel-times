#!/usr/bin/env python
# Recompute brand for each product in seed_data.json using substring + model-name matching.
import json, re
from collections import Counter

P = r"C:\xampp\htdocs\watches\data\seed_data.json"

# Canonical brands, longest-first so multi-word brands win.
BRANDS = [
    "Audemars Piguet","Patek Philippe","Vacheron Constantin","Jaeger-LeCoultre",
    "A. Lange & Sohne","Richard Mille","Franck Muller","Ulysse Nardin","Grand Seiko",
    "Tag Heuer","TAG Heuer","Bell & Ross","Roger Dubuis","Girard-Perregaux","Maurice Lacroix",
    "Rolex","Omega","Cartier","Hublot","Breitling","Panerai","IWC","Tudor","Longines",
    "Tissot","Zenith","Chopard","Montblanc","Blancpain","Breguet","Piaget","Glashutte",
    "Hamilton","Seiko","Rado","Oris","Corum","Graham","U-Boat","Bvlgari","Bulgari",
    "Chanel","Hermes","Parmigiani",
]
ALIASES = {  # misspellings / variants found in the data
    "audemars pigeut": "Audemars Piguet",
    "tag heuer": "TAG Heuer",
    "bulgari": "Bvlgari",
}
# Model keyword -> brand, for names that omit the maison.
MODEL_HINTS = [
    (["submariner","daytona","datejust","day-date","day date","gmt-master","gmt master",
      "yacht-master","yacht master","sea-dweller","sky-dweller","land-dweller","air-king",
      "milgauss","cellini","oyster perpetual","deepsea","turn-o-graph","explorer"], "Rolex"),
    (["nautilus","aquanaut","calatrava","twenty~4","grand complication"], "Patek Philippe"),
    (["royal oak","code 11.59","code 11-59"], "Audemars Piguet"),
    (["speedmaster","seamaster","aqua terra","planet ocean","constellation","de ville"], "Omega"),
    (["big bang","classic fusion","spirit of big bang"], "Hublot"),
    (["santos","ballon bleu","panth","pasha","ronde","tank","drive de"], "Cartier"),
    (["luminor","radiomir","submersible"], "Panerai"),
    (["navitimer","avenger","superocean","chronomat","endurance pro","premier"], "Breitling"),
    (["overseas","patrimony"], "Vacheron Constantin"),
    (["reverso"], "Jaeger-LeCoultre"),
]

def detect(name: str) -> str:
    low = " " + name.lower() + " "
    for a, b in ALIASES.items():
        if a in low:
            return b
    for b in BRANDS:  # word-ish substring
        if b.lower() in low:
            return "TAG Heuer" if b.lower() == "tag heuer" else ("Bvlgari" if b.lower()=="bulgari" else b)
    for kws, b in MODEL_HINTS:
        for k in kws:
            if k in low:
                return b
    return "Other"

d = json.load(open(P, encoding="utf-8"))
changed = 0
for p in d["products"]:
    nb = detect(p["name"])
    if nb != p["brand"]:
        changed += 1
    p["brand"] = nb
d["brands"] = sorted({p["brand"] for p in d["products"]})
json.dump(d, open(P, "w", encoding="utf-8"), ensure_ascii=False)

c = Counter(p["brand"] for p in d["products"])
print(f"reassigned: {changed}")
print(f"brands ({len(c)}):")
for b, n in c.most_common():
    print(f"   {n:4d}  {b}")
