using Microsoft.AspNetCore.Mvc.RazorPages;

namespace DeploymentDashboard.Pages;

public class AboutModel : PageModel
{
    private readonly IConfiguration _configuration;

    public AboutModel(IConfiguration configuration) => _configuration = configuration;

    public string StudentName => _configuration["Student:FullName"] ?? "Student Name";
    public string StudentId => _configuration["Student:Id"] ?? "Student ID";
}
