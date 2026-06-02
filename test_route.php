
Route::get("/debug-request", function(\Illuminate\Http\Request $req) {
    return [
        "url" => $req->url(),
        "fullUrl" => $req->fullUrl(),
        "scheme" => $req->getScheme(),
        "isSecure" => $req->secure(),
        "headers" => $req->headers->all()
    ];
});

