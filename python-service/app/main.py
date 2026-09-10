from fastapi import FastAPI, Depends
from app.dependencies import verify_internal_key
from app.routers import profile, extract, clean, validate, export

app = FastAPI(
    title="Data Science Tool Python Service",
    dependencies=[Depends(verify_internal_key)]
)

app.include_router(profile.router)
app.include_router(extract.router)
app.include_router(clean.router)
app.include_router(validate.router)
app.include_router(export.router)

@app.get("/health")
async def health_check():
    return {"status": "ok"}
