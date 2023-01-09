<table>
  <thead>
  <tr>
      <th>#</th>
      <th>Vend ID</th>
      <th>Date Time</th>
      <th>Temp</th>
      <th>Type</th>
  </tr>
  </thead>
  <tbody>
  @foreach($vendTemps as $vendTempIndex => $vendTemp)
      <tr>
          <td>
            {{ $vendTempIndex + 1 }}
          </td>
          <td>
            {{ $vendTemp->vend->code }}
          </td>
          <td>
            {{ $vendTemp->created_at }}
          </td>
          <td>
            {{ $vendTemp->value/ 10 }}
          </td>
          <td>
            {{ 'T'.$vendTemp->type }}
          </td>

      </tr>
  @endforeach
  </tbody>
</table>