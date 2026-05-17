import pathlib

close = "</" + "div" + ">"
open_ = "<" + "motion"
open_fix = "<" + "div"

for path in pathlib.Path(__file__).resolve().parents[1].joinpath("resources/js").rglob("*.vue"):
    if "Admin" not in str(path):
        continue
    text = path.read_text(encoding="utf-8")
    updated = text.replace(open_, open_fix).replace("</" + "motion>", close)
    if updated != text:
        path.write_text(updated, encoding="utf-8")
        print(path)
