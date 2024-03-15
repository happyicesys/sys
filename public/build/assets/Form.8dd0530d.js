import{h as c,T as i,g as f,f as v,a as r,u as _,w as t,F as w,o as g,Z as x,b as o,e as V,d as l}from"./app.772f9cda.js";import{_ as h}from"./Authenticated.06d2d34c.js";import{_ as n}from"./FormInput.d3af3c8a.js";import"./keyboard.95c1f932.js";import"./use-resolve-button-type.186d21c3.js";import"./RectangleStackIcon.5069dd01.js";const b=o("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Account Settings ",-1),y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 pt-5 md:pt-2"},S={class:"overflow-hidden shadow sm:rounded-md"},U={class:"bg-white px-4 py-5 sm:p-6"},k={class:"grid grid-cols-12 gap-6"},F={class:"col-span-12 sm:col-span-8"},N={class:"col-span-12 sm:col-span-6"},T={class:"col-span-12 sm:col-span-6"},B={class:"col-span-12 sm:col-span-6"},E={class:"col-span-12 sm:col-span-6"},j=o("div",{class:"bg-gray-50 px-4 py-3 text-right sm:px-6"},[o("button",{type:"submit",class:"inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},"Save")],-1),D={__name:"Form",props:{user:Object},setup(u){c(()=>{e.value=m.user?i({...d(),...m.user.data}):i(d())});const m=u,e=f(i(d()));function d(){return{id:"",name:"",username:"",email:"",password:"",password_confirmation:""}}function p(){e.value.clearErrors(),e.value.post("/self/"+e.value.id+"/update",{preserveState:!0,replace:!0})}return(A,s)=>(g(),v(w,null,[r(_(x),{title:"Account Settings"}),r(h,null,{header:t(()=>[b]),default:t(()=>[o("div",y,[o("form",{onSubmit:V(p,["prevent"])},[o("div",S,[o("div",U,[o("div",k,[o("div",F,[r(n,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=a=>e.value.name=a),error:e.value.errors.name,required:"true"},{default:t(()=>[l(" Name ")]),_:1},8,["modelValue","error"])]),o("div",N,[r(n,{modelValue:e.value.email,"onUpdate:modelValue":s[1]||(s[1]=a=>e.value.email=a),error:e.value.errors.email},{default:t(()=>[l(" Email ")]),_:1},8,["modelValue","error"])]),o("div",T,[r(n,{modelValue:e.value.username,"onUpdate:modelValue":s[2]||(s[2]=a=>e.value.username=a),error:e.value.errors.username},{default:t(()=>[l(" Username ")]),_:1},8,["modelValue","error"])]),o("div",B,[r(n,{modelValue:e.value.password,"onUpdate:modelValue":s[3]||(s[3]=a=>e.value.password=a),error:e.value.errors.password,placeholderStr:"Leave blank for same password",inputType:"password",autocomplete:"new-password"},{default:t(()=>[l(" Password ")]),_:1},8,["modelValue","error"])]),o("div",E,[r(n,{modelValue:e.value.password_confirmation,"onUpdate:modelValue":s[4]||(s[4]=a=>e.value.password_confirmation=a),error:e.value.errors.password_confirmation,placeholderStr:"Leave blank for same password",inputType:"password",autocomplete:"new-password-confirmation"},{default:t(()=>[l(" Password Confirmation ")]),_:1},8,["modelValue","error"])])])]),j])],32)])]),_:1})],64))}};export{D as default};