using System.Text.Json;
using DeploymentDashboard.Models;
using DeploymentDashboard.Services;
using Microsoft.AspNetCore.HttpOverrides;
using Microsoft.AspNetCore.Diagnostics.HealthChecks;
using Microsoft.Extensions.Diagnostics.HealthChecks;

var builder = WebApplication.CreateBuilder(args);

// ---------------------------------------------------------------------------
// Services
// ---------------------------------------------------------------------------
builder.Services.AddRazorPages();
builder.Services.AddSingleton<IDeploymentInfoProvider, DeploymentInfoProvider>();
builder.Services.AddSingleton<ITaskStore, InMemoryTaskStore>();
builder.Services.AddHealthChecks();

var app = builder.Build();

// ---------------------------------------------------------------------------
// HTTP pipeline
//
// Azure App Service terminates TLS at the front end and forwards plain HTTP to
// the container, so the original scheme only survives in X-Forwarded-Proto.
// The forwarded-headers middleware restores it before anything else looks at the
// request; without it Request.IsHttps is always false and every URL the
// application renders points at http://.
// ---------------------------------------------------------------------------
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error");
    app.UseHsts();
}

var forwardedHeaders = new ForwardedHeadersOptions
{
    ForwardedHeaders = ForwardedHeaders.XForwardedFor | ForwardedHeaders.XForwardedProto
};

// The App Service front end is not a known proxy inside the container, so the
// default loopback-only allow list has to be cleared or the headers are ignored.
forwardedHeaders.KnownNetworks.Clear();
forwardedHeaders.KnownProxies.Clear();
app.UseForwardedHeaders(forwardedHeaders);

// Only redirect to HTTPS when the app itself is listening on an HTTPS endpoint.
// In App Service the redirect happens at the platform edge (httpsOnly=true), and
// calling this there only produces a startup warning:
//   "Failed to determine the https port for redirect."
var runningInAzure = !string.IsNullOrEmpty(Environment.GetEnvironmentVariable("WEBSITE_SITE_NAME"));
if (!runningInAzure)
{
    app.UseHttpsRedirection();
}

app.UseRouting();
app.UseAuthorization();

app.MapStaticAssets();

app.MapRazorPages()
   .WithStaticAssets();

// ---------------------------------------------------------------------------
// Machine readable endpoints used as deployment evidence.
// ---------------------------------------------------------------------------
app.MapGet("/api/deployment", (IDeploymentInfoProvider provider) =>
    Results.Json(provider.Get(), new JsonSerializerOptions { WriteIndented = true }));

app.MapHealthChecks("/health", new HealthCheckOptions
{
    ResponseWriter = async (context, report) =>
    {
        context.Response.ContentType = "application/json";
        var payload = new
        {
            status = report.Status.ToString(),
            totalDurationMs = report.TotalDuration.TotalMilliseconds,
            checks = report.Entries.Select(e => new
            {
                name = e.Key,
                status = e.Value.Status.ToString(),
                durationMs = e.Value.Duration.TotalMilliseconds,
                description = e.Value.Description
            })
        };
        await context.Response.WriteAsync(JsonSerializer.Serialize(payload,
            new JsonSerializerOptions { WriteIndented = true }));
    }
});

app.Run();
