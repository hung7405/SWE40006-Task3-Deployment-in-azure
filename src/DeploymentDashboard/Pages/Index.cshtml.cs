using DeploymentDashboard.Models;
using DeploymentDashboard.Services;
using Microsoft.AspNetCore.Mvc.RazorPages;

namespace DeploymentDashboard.Pages;

public class IndexModel : PageModel
{
    private readonly IDeploymentInfoProvider _provider;

    public IndexModel(IDeploymentInfoProvider provider) => _provider = provider;

    public DeploymentInfo Info { get; private set; } = default!;

    public string RequestId { get; private set; } = string.Empty;

    public void OnGet()
    {
        Info = _provider.Get();
        RequestId = HttpContext.TraceIdentifier;
    }
}
